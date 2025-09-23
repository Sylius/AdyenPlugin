<?php

/*
 * This file is part of the Sylius Adyen Plugin package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Sylius\AdyenPlugin\Unit\Provider;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Exception\AdyenPaymentMethodNotFoundException;
use Sylius\AdyenPlugin\Exception\OrderWithoutBillingAddressException;
use Sylius\AdyenPlugin\Model\PaymentMethodData;
use Sylius\AdyenPlugin\Provider\CurrentShopUserProviderInterface;
use Sylius\AdyenPlugin\Provider\DropinConfigurationProvider;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DropinConfigurationProviderTest extends TestCase
{
    private MockObject|PaymentMethodRepositoryInterface $paymentMethodRepository;

    private MockObject|PaymentMethodsProviderInterface $paymentMethodsProvider;

    private CurrentShopUserProviderInterface|MockObject $currentShopUserProvider;

    private MockObject|UrlGeneratorInterface $urlGenerator;

    private MockObject|TranslatorInterface $translator;

    private DropinConfigurationProvider $provider;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->paymentMethodsProvider = $this->createMock(PaymentMethodsProviderInterface::class);
        $this->currentShopUserProvider = $this->createMock(CurrentShopUserProviderInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->provider = new DropinConfigurationProvider(
            $this->paymentMethodRepository,
            $this->paymentMethodsProvider,
            $this->currentShopUserProvider,
            $this->urlGenerator,
            $this->translator,
        );
    }

    public function testThrowsExceptionWhenPaymentMethodNotFound(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $paymentMethodCode = 'non_existent_method';

        $this->paymentMethodRepository
            ->expects(self::once())
            ->method('getOneAdyenForCode')
            ->with($paymentMethodCode)
            ->willReturn(null);

        $this->expectException(AdyenPaymentMethodNotFoundException::class);

        $this->provider->getConfiguration($order, $paymentMethodCode);
    }

    public function testThrowsExceptionWhenOrderHasNoBillingAddress(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethodCode = 'adyen_card';

        $this->paymentMethodRepository
            ->expects(self::once())
            ->method('getOneAdyenForCode')
            ->with($paymentMethodCode)
            ->willReturn($paymentMethod);

        $order
            ->expects(self::once())
            ->method('getBillingAddress')
            ->willReturn(null);

        $this->expectException(OrderWithoutBillingAddressException::class);

        $this->provider->getConfiguration($order, $paymentMethodCode);
    }

    #[DataProvider('provideStoreDetailsScenarios')]
    public function testStoreDetailsConfiguration(
        bool $hasCurrentShopUser,
        bool $hasCustomer,
        ?string $customerUserType,
        bool $result,
    ): void {
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $billingAddress = $this->createMock(AddressInterface::class);
        $paymentMethodCode = 'adyen_card';
        $tokenValue = 'token123';

        $currentShopUser = $hasCurrentShopUser ? $this->createMock(ShopUserInterface::class) : null;
        $customer = $hasCustomer ? $this->createMock(CustomerInterface::class) : null;

        $customerUser = match ($customerUserType) {
            'shopUser' => $currentShopUser,
            'anotherShopUser' => $this->createMock(ShopUserInterface::class),
            default => null,
        };

        $this->setupBasicMocks(
            $order,
            $paymentMethod,
            $gatewayConfig,
            $billingAddress,
            $paymentMethodCode,
            $tokenValue,
            $customer,
            $customerUser,
        );

        $this->currentShopUserProvider
            ->expects(self::once())
            ->method('getShopUser')
            ->willReturn($currentShopUser);

        $configuration = $this->provider->getConfiguration($order, $paymentMethodCode);

        self::assertSame($result, $configuration['enableStoreDetails']);
    }

    public static function provideStoreDetailsScenarios(): iterable
    {
        yield 'guest user without customer' => [
            'hasCurrentShopUser' => false,
            'hasCustomer' => false,
            'customerUser' => null,
            'result' => false,
        ];

        yield 'guest user with customer without user' => [
            'hasCurrentShopUser' => false,
            'hasCustomer' => true,
            'customerUser' => null,
            'result' => false,
        ];

        yield 'guest user with customer with user' => [
            'hasCurrentShopUser' => false,
            'hasCustomer' => true,
            'customerUser' => 'shopUser',
            'result' => false,
        ];

        yield 'logged in user ordering for themselves' => [
            'hasCurrentShopUser' => true,
            'hasCustomer' => true,
            'customerUser' => 'shopUser',
            'result' => true,
        ];

        yield 'logged in user ordering for someone else' => [
            'hasCurrentShopUser' => true,
            'hasCustomer' => true,
            'customerUser' => 'anotherShopUser',
            'result' => false,
        ];

        yield 'logged in user with order without customer' => [
            'hasCurrentShopUser' => true,
            'hasCustomer' => false,
            'customerUser' => null,
            'result' => false,
        ];
    }

    #[DataProvider('provideBillingAddressData')]
    public function testBillingAddressConfiguration(
        ?string $provinceName,
        ?string $provinceCode,
        ?string $expectedProvince,
    ): void {
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $billingAddress = $this->createMock(AddressInterface::class);
        $paymentMethodCode = 'adyen_card';
        $tokenValue = 'token123';

        $this->setupBasicMocks(
            $order,
            $paymentMethod,
            $gatewayConfig,
            $billingAddress,
            $paymentMethodCode,
            $tokenValue,
        );

        $billingAddress->expects(self::once())->method('getProvinceName')->willReturn($provinceName);
        if ($provinceName === null) {
            $billingAddress->expects(self::once())->method('getProvinceCode')->willReturn($provinceCode);
        } else {
            $billingAddress->expects(self::never())->method('getProvinceCode');
        }

        $configuration = $this->provider->getConfiguration($order, $paymentMethodCode);

        self::assertSame($expectedProvince, $configuration['billingAddress']['province']);
    }

    public static function provideBillingAddressData(): iterable
    {
        yield 'province with name' => [
            'provinceName' => 'California',
            'provinceCode' => 'CA',
            'expectedProvince' => 'California',
        ];

        yield 'province with code only' => [
            'provinceName' => null,
            'provinceCode' => 'CA',
            'expectedProvince' => 'CA',
        ];

        yield 'province with neither name nor code' => [
            'provinceName' => null,
            'provinceCode' => null,
            'expectedProvince' => null,
        ];
    }

    #[DataProvider('provideEnvironmentData')]
    public function testEnvironmentConfiguration(string $environment): void
    {
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $billingAddress = $this->createMock(AddressInterface::class);
        $paymentMethodCode = 'adyen_card';
        $tokenValue = 'token123';

        $this->setupBasicMocks(
            $order,
            $paymentMethod,
            $gatewayConfig,
            $billingAddress,
            $paymentMethodCode,
            $tokenValue,
            null,
            null,
            $environment,
        );

        $configuration = $this->provider->getConfiguration($order, $paymentMethodCode);

        self::assertSame($environment, $configuration['environment']);
        self::assertSame('test_client_key', $configuration['clientKey']);
    }

    public static function provideEnvironmentData(): iterable
    {
        yield 'test environment' => ['test'];
        yield 'live environment' => ['live'];
    }

    public function testFullConfiguration(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $billingAddress = $this->createMock(AddressInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $shopUser = $this->createMock(ShopUserInterface::class);
        $paymentMethodCode = 'adyen_card';
        $tokenValue = 'token123';
        $localeCode = 'en_US';
        $currencyCode = 'USD';
        $total = 10000;

        $this->paymentMethodRepository
            ->expects(self::once())
            ->method('getOneAdyenForCode')
            ->with($paymentMethodCode)
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects(self::once())
            ->method('getConfig')
            ->willReturn([
                'clientKey' => 'test_client_key',
                'environment' => 'test',
            ]);

        $order->expects(self::once())->method('getBillingAddress')->willReturn($billingAddress);
        $order->expects(self::once())->method('getCustomer')->willReturn($customer);
        $order->expects(self::once())->method('getTokenValue')->willReturn($tokenValue);
        $order->expects(self::once())->method('getLocaleCode')->willReturn($localeCode);
        $order->expects(self::once())->method('getCurrencyCode')->willReturn($currencyCode);
        $order->expects(self::once())->method('getTotal')->willReturn($total);

        $customer->expects(self::once())->method('getUser')->willReturn($shopUser);

        $this->currentShopUserProvider
            ->expects(self::once())
            ->method('getShopUser')
            ->willReturn($shopUser);

        $billingAddress->expects(self::once())->method('getFirstName')->willReturn('John');
        $billingAddress->expects(self::once())->method('getLastName')->willReturn('Doe');
        $billingAddress->expects(self::once())->method('getCountryCode')->willReturn('US');
        $billingAddress->expects(self::once())->method('getProvinceName')->willReturn('California');
        $billingAddress->expects(self::once())->method('getCity')->willReturn('Los Angeles');
        $billingAddress->expects(self::once())->method('getPostcode')->willReturn('90001');

        $paymentMethods = new PaymentMethodData(
            paymentMethods: ['card', 'paypal'],
            storedPaymentMethods: ['stored_card_1'],
        );

        $this->paymentMethodsProvider
            ->expects(self::once())
            ->method('provideForOrder')
            ->with($paymentMethod, $order)
            ->willReturn($paymentMethods);

        $pathParams = [
            'code' => $paymentMethodCode,
            'tokenValue' => $tokenValue,
        ];

        $this->urlGenerator
            ->expects($this->exactly(3))
            ->method('generate')
            ->willReturnCallback(function ($route, $params) use ($pathParams) {
                return match ($route) {
                    'sylius_adyen_shop_payments' => '/payments',
                    'sylius_adyen_shop_payment_details' => '/payment-details',
                    'sylius_adyen_shop_remove_token' => '/remove-token',
                    default => throw new \Exception("Unexpected route: $route"),
                };
            });

        $this->translator
            ->expects(self::once())
            ->method('trans')
            ->with('sylius_adyen.runtime.payment_failed_try_again')
            ->willReturn('Payment failed. Please try again.');

        $configuration = $this->provider->getConfiguration($order, $paymentMethodCode);

        self::assertIsArray($configuration);

        // Check billing address
        self::assertArrayHasKey('billingAddress', $configuration);
        self::assertSame('John', $configuration['billingAddress']['firstName']);
        self::assertSame('Doe', $configuration['billingAddress']['lastName']);
        self::assertSame('US', $configuration['billingAddress']['countryCode']);
        self::assertSame('California', $configuration['billingAddress']['province']);
        self::assertSame('Los Angeles', $configuration['billingAddress']['city']);
        self::assertSame('90001', $configuration['billingAddress']['postcode']);

        // Check payment methods
        self::assertArrayHasKey('paymentMethods', $configuration);
        self::assertSame($paymentMethods, $configuration['paymentMethods']);

        // Check configuration
        self::assertSame('test_client_key', $configuration['clientKey']);
        self::assertSame('en_US', $configuration['locale']);
        self::assertSame('test', $configuration['environment']);
        self::assertTrue($configuration['enableStoreDetails']);

        // Check amount
        self::assertArrayHasKey('amount', $configuration);
        self::assertSame('USD', $configuration['amount']['currency']);
        self::assertSame(10000, $configuration['amount']['value']);

        // Check paths
        self::assertArrayHasKey('path', $configuration);
        self::assertSame('/payments', $configuration['path']['payments']);
        self::assertSame('/payment-details', $configuration['path']['paymentDetails']);
        self::assertSame('/remove-token', $configuration['path']['deleteToken']);

        // Check translations
        self::assertArrayHasKey('translations', $configuration);
        self::assertSame(
            'Payment failed. Please try again.',
            $configuration['translations']['sylius_adyen.runtime.payment_failed_try_again'],
        );
    }

    public function testTranslationsAreIncluded(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $billingAddress = $this->createMock(AddressInterface::class);
        $paymentMethodCode = 'adyen_card';
        $tokenValue = 'token123';

        $this->setupBasicMocks(
            $order,
            $paymentMethod,
            $gatewayConfig,
            $billingAddress,
            $paymentMethodCode,
            $tokenValue,
            null,
            null,
            'test',
            false,  // Don't use default translation mock
        );

        $this->translator
            ->expects(self::once())
            ->method('trans')
            ->with('sylius_adyen.runtime.payment_failed_try_again')
            ->willReturn('Translated message');

        $configuration = $this->provider->getConfiguration($order, $paymentMethodCode);

        self::assertArrayHasKey('translations', $configuration);
        self::assertArrayHasKey('sylius_adyen.runtime.payment_failed_try_again', $configuration['translations']);
        self::assertSame(
            'Translated message',
            $configuration['translations']['sylius_adyen.runtime.payment_failed_try_again'],
        );
    }

    private function setupBasicMocks(
        MockObject|OrderInterface $order,
        MockObject|PaymentMethodInterface $paymentMethod,
        GatewayConfigInterface|MockObject $gatewayConfig,
        AddressInterface|MockObject $billingAddress,
        string $paymentMethodCode,
        string $tokenValue,
        CustomerInterface|MockObject|null $customer = null,
        MockObject|ShopUserInterface|null $customerUser = null,
        string $environment = 'test',
        bool $mockTranslator = true,
    ): void {
        $this->paymentMethodRepository
            ->expects(self::once())
            ->method('getOneAdyenForCode')
            ->with($paymentMethodCode)
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->method('getConfig')
            ->willReturn([
                'clientKey' => 'test_client_key',
                'environment' => $environment,
            ]);

        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getCustomer')->willReturn($customer);
        $order->method('getTokenValue')->willReturn($tokenValue);
        $order->method('getLocaleCode')->willReturn('en_US');
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getTotal')->willReturn(10000);

        if ($customer !== null) {
            $customer->method('getUser')->willReturn($customerUser);
        }

        $billingAddress->method('getFirstName')->willReturn('John');
        $billingAddress->method('getLastName')->willReturn('Doe');
        $billingAddress->method('getCountryCode')->willReturn('US');
        $billingAddress->method('getCity')->willReturn('Los Angeles');
        $billingAddress->method('getPostcode')->willReturn('90001');

        $paymentMethods = new PaymentMethodData(
            paymentMethods: ['card'],
            storedPaymentMethods: [],
        );

        $this->paymentMethodsProvider
            ->method('provideForOrder')
            ->willReturn($paymentMethods);

        $this->urlGenerator
            ->method('generate')
            ->willReturn('/test-url');

        if ($mockTranslator) {
            $this->translator
                ->method('trans')
                ->willReturnArgument(0);
        }
    }
}
