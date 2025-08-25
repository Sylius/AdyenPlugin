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

namespace Tests\Sylius\AdyenPlugin\Unit\Client;

use Adyen\Model\Checkout\PaymentLinkRequest;
use Doctrine\Common\Collections\ArrayCollection;
use Payum\Core\Bridge\Spl\ArrayObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\EsdCardPaymentSupportCheckerInterface;
use Sylius\AdyenPlugin\Client\ClientPayloadFactory;
use Sylius\AdyenPlugin\Collector\CompositeEsdCollector;
use Sylius\AdyenPlugin\Collector\CompositeEsdCollectorInterface;
use Sylius\AdyenPlugin\Collector\ItemDetailLineCollector;
use Sylius\AdyenPlugin\Collector\Level2EsdCollector;
use Sylius\AdyenPlugin\Collector\Level3EsdCollector;
use Sylius\AdyenPlugin\Resolver\Version\VersionResolverInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ClientPayloadFactoryTest extends TestCase
{
    private ClientPayloadFactory $factory;

    private VersionResolverInterface $versionResolver;

    private NormalizerInterface $normalizer;

    private RequestStack $requestStack;

    private CompositeEsdCollectorInterface $esdCollector;

    protected function setUp(): void
    {
        $this->versionResolver = $this->createMock(VersionResolverInterface::class);
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $level2Collector = new Level2EsdCollector();
        $itemDetailLineCollector = new ItemDetailLineCollector();
        $cardChecker = $this->createMock(EsdCardPaymentSupportCheckerInterface::class);
        $cardChecker->method('isSupported')->willReturn(true);

        $this->esdCollector = new CompositeEsdCollector(
            new \ArrayIterator([
                'level2' => $level2Collector,
                'level3' => new Level3EsdCollector($level2Collector, $itemDetailLineCollector),
            ]),
            ['USD'],
            ['US'],
            $cardChecker,
        );

        $this->factory = new ClientPayloadFactory(
            $this->versionResolver,
            $this->normalizer,
            $this->requestStack,
            $this->esdCollector,
        );

        $this->versionResolver->expects($this->any())
            ->method('appendVersionConstraints')
            ->willReturnArgument(0)
        ;
    }

    public function testItAddsEsdForCardPaymentsInSubmitPayment(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => true,
            'esdType' => 'level3',
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())->method('getCountryCode')->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->any())->method('getTotal')->willReturn(10000);
        $order->expects($this->any())->method('getCurrencyCode')->willReturn('USD');
        $order->expects($this->any())->method('getNumber')->willReturn('000001');
        $order->expects($this->any())->method('getBillingAddress')->willReturn($billingAddress);

        $receivedPayload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
            ],
        ];

        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->any())->method('getId')->willReturn(123);

        $order->expects($this->any())->method('getCustomer')->willReturn($customer);
        $order->expects($this->any())->method('getTaxTotal')->willReturn(100);
        $order->expects($this->any())->method('getShippingAddress')->willReturn(null);
        $order->expects($this->any())->method('getItems')->willReturn(new ArrayCollection());
        $order->expects($this->any())->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $order->expects($this->any())->method('getShippingTotal')->willReturn(500);

        $result = $this->factory->createForSubmitPayment(
            $options,
            'https://example.com/return',
            $receivedPayload,
            $order,
        );

        self::assertArrayHasKey('additionalData', $result);
        self::assertEquals('123', $result['additionalData']['enhancedSchemeData.customerReference']);
        self::assertEquals(100, $result['additionalData']['enhancedSchemeData.totalTaxAmount']);
    }

    public function testItDoesNotAddEsdForNonCardPayments(): void
    {
        // Create special collector that returns false for non-card payments
        $level2Collector = new Level2EsdCollector();
        $itemDetailLineCollector = new ItemDetailLineCollector();
        $cardChecker = $this->createMock(EsdCardPaymentSupportCheckerInterface::class);
        $cardChecker->method('isSupported')->willReturn(false);

        $esdCollector = new CompositeEsdCollector(
            new \ArrayIterator([
                'level2' => $level2Collector,
                'level3' => new Level3EsdCollector($level2Collector, $itemDetailLineCollector),
            ]),
            ['USD'],
            ['US'],
            $cardChecker,
        );

        $factory = new ClientPayloadFactory(
            $this->versionResolver,
            $this->normalizer,
            $this->requestStack,
            $esdCollector,
        );

        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => true,
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())->method('getCountryCode')->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->any())->method('getTotal')->willReturn(10000);
        $order->expects($this->any())->method('getCurrencyCode')->willReturn('USD');
        $order->expects($this->any())->method('getNumber')->willReturn('000002');
        $order->expects($this->any())->method('getBillingAddress')->willReturn($billingAddress);

        $receivedPayload = [
            'paymentMethod' => [
                'type' => 'ideal',
            ],
        ];

        $result = $factory->createForSubmitPayment(
            $options,
            'https://example.com/return',
            $receivedPayload,
            $order,
        );

        self::assertArrayNotHasKey('enhancedSchemeData.customerReference', $result['additionalData'] ?? []);
    }

    public function testItDoesNotAddEsdWhenDisabled(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => false,
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())->method('getCountryCode')->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->any())->method('getTotal')->willReturn(10000);
        $order->expects($this->any())->method('getCurrencyCode')->willReturn('USD');
        $order->expects($this->any())->method('getNumber')->willReturn('000003');
        $order->expects($this->any())->method('getBillingAddress')->willReturn($billingAddress);

        $receivedPayload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
            ],
        ];

        $result = $this->factory->createForSubmitPayment(
            $options,
            'https://example.com/return',
            $receivedPayload,
            $order,
        );

        self::assertArrayNotHasKey('enhancedSchemeData.customerReference', $result['additionalData'] ?? []);
    }

    public function testItAddsEsdForCaptureRequests(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => true,
        ]);

        $order = $this->createMock(OrderInterface::class);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())->method('getAmount')->willReturn(10000);
        $payment->expects($this->once())->method('getCurrencyCode')->willReturn('USD');
        $payment
            ->expects($this->any())
            ->method('getDetails')
            ->willReturn([
                'pspReference' => 'PSP123',
                'paymentMethod' => ['brand' => 'mc'],
            ])
        ;
        $payment->expects($this->once())->method('getOrder')->willReturn($order);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->any())->method('getId')->willReturn(456);

        $order->expects($this->any())->method('getCurrencyCode')->willReturn('USD');
        $order->expects($this->any())->method('getCustomer')->willReturn($customer);
        $order->expects($this->any())->method('getTaxTotal')->willReturn(200);
        $order->expects($this->any())->method('getShippingAddress')->willReturn(null);
        $order->expects($this->any())->method('getItems')->willReturn(new ArrayCollection());
        $order->expects($this->any())->method('getCreatedAt')->willReturn(new \DateTime('2023-01-01'));
        $order->expects($this->any())->method('getShippingTotal')->willReturn(500);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())->method('getCountryCode')->willReturn('US');
        $order->expects($this->any())->method('getBillingAddress')->willReturn($billingAddress);

        $result = $this->factory->createForCapture($options, $payment);

        self::assertArrayHasKey('additionalData', $result);
        self::assertEquals('456', $result['additionalData']['enhancedSchemeData.customerReference']);
        self::assertEquals(200, $result['additionalData']['enhancedSchemeData.totalTaxAmount']);
    }

    public function testItDoesNotAddEsdForNonVisaMastercardBrands(): void
    {
        // Create special collector that returns false for unsupported card brands
        $level2Collector = new Level2EsdCollector();
        $itemDetailLineCollector = new ItemDetailLineCollector();
        $cardChecker = $this->createMock(EsdCardPaymentSupportCheckerInterface::class);
        $cardChecker->method('isSupported')->willReturn(false);

        $esdCollector = new CompositeEsdCollector(
            new \ArrayIterator([
                'level2' => $level2Collector,
                'level3' => new Level3EsdCollector($level2Collector, $itemDetailLineCollector),
            ]),
            ['USD'],
            ['US'],
            $cardChecker,
        );

        $factory = new ClientPayloadFactory(
            $this->versionResolver,
            $this->normalizer,
            $this->requestStack,
            $esdCollector,
        );

        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => true,
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())->method('getCountryCode')->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->any())->method('getTotal')->willReturn(10000);
        $order->expects($this->any())->method('getCurrencyCode')->willReturn('USD');
        $order->expects($this->any())->method('getNumber')->willReturn('000004');
        $order->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($billingAddress);

        $receivedPayload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'amex',
            ],
        ];

        $result = $factory->createForSubmitPayment(
            $options,
            'https://example.com/return',
            $receivedPayload,
            $order,
        );

        self::assertArrayNotHasKey('enhancedSchemeData.customerReference', $result['additionalData'] ?? []);
    }

    public function testItCreatesPaymentLinkWithBasicOrderData(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->once())->method('getCountryCode')->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())->method('getNumber')->willReturn('000005');
        $order->expects($this->once())->method('getBillingAddress')->willReturn($billingAddress);
        $order->expects($this->once())->method('getLocaleCode')->willReturn(null);

        $this->normalizer->expects($this->once())
            ->method('normalize')
            ->with($order)
            ->willReturn([
                'amount' => ['value' => 10000, 'currency' => 'USD'],
                'shopperEmail' => 'test@example.com',
                'shopperIp' => '127.0.0.1',
            ]);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())->method('getOrder')->willReturn($order);

        $result = $this->factory->createForPaymentLink($options, $payment);

        self::assertInstanceOf(PaymentLinkRequest::class, $result);
        $resultArray = $result->toArray();

        self::assertEquals('000005', $resultArray['reference']);
        self::assertEquals('TestMerchant', $resultArray['merchantAccount']);
        self::assertEquals('US', $resultArray['countryCode']);
        self::assertEquals(['value' => 10000, 'currency' => 'USD'], $resultArray['amount']);
        self::assertEquals('test@example.com', $resultArray['shopperEmail']);
        self::assertArrayNotHasKey('shopperIp', $resultArray);
        self::assertArrayNotHasKey('shopperLocale', $resultArray);
    }

    public function testItCreatesPaymentLinkWithLocaleCode(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->once())->method('getCountryCode')->willReturn('FR');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())->method('getNumber')->willReturn('000006');
        $order->expects($this->once())->method('getBillingAddress')->willReturn($billingAddress);
        $order->expects($this->exactly(2))->method('getLocaleCode')->willReturn('fr_FR');

        $this->normalizer->expects($this->once())
            ->method('normalize')
            ->with($order)
            ->willReturn([
                'amount' => ['value' => 20000, 'currency' => 'EUR'],
                'shopperEmail' => 'test@example.fr',
            ]);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())->method('getOrder')->willReturn($order);

        $result = $this->factory->createForPaymentLink($options, $payment);

        self::assertInstanceOf(PaymentLinkRequest::class, $result);
        $resultArray = $result->toArray();

        self::assertEquals('000006', $resultArray['reference']);
        self::assertEquals('TestMerchant', $resultArray['merchantAccount']);
        self::assertEquals('FR', $resultArray['countryCode']);
        self::assertEquals(['value' => 20000, 'currency' => 'EUR'], $resultArray['amount']);
        self::assertEquals('test@example.fr', $resultArray['shopperEmail']);
        self::assertEquals('fr-FR', $resultArray['shopperLocale']);
    }

    public function testItCreatesPaymentLinkWithoutBillingAddress(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
        ]);

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())->method('getNumber')->willReturn('000007');
        $order->expects($this->once())->method('getBillingAddress')->willReturn(null);
        $order->expects($this->once())->method('getLocaleCode')->willReturn(null);

        $this->normalizer->expects($this->once())
            ->method('normalize')
            ->with($order)
            ->willReturn([
                'amount' => ['value' => 15000, 'currency' => 'GBP'],
                'shopperEmail' => 'test@example.uk',
            ]);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())->method('getOrder')->willReturn($order);

        $result = $this->factory->createForPaymentLink($options, $payment);

        self::assertInstanceOf(PaymentLinkRequest::class, $result);
        $resultArray = $result->toArray();

        self::assertEquals('000007', $resultArray['reference']);
        self::assertEquals('TestMerchant', $resultArray['merchantAccount']);
        self::assertEquals('ZZ', $resultArray['countryCode']); // NO_COUNTRY_AVAILABLE_PLACEHOLDER
        self::assertEquals(['value' => 15000, 'currency' => 'GBP'], $resultArray['amount']);
        self::assertEquals('test@example.uk', $resultArray['shopperEmail']);
        self::assertArrayNotHasKey('shopperLocale', $resultArray);
    }
}
