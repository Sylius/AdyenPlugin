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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodChecker;
use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class AdyenPaymentMethodCheckerTest extends TestCase
{
    /** @var MockObject|RepositoryInterface<AdyenPaymentDetailInterface> */
    private MockObject|RepositoryInterface $adyenPaymentDetailRepository;

    private MockObject|PaymentLinkRepositoryInterface $paymentLinkRepository;

    protected function setUp(): void
    {
        $this->adyenPaymentDetailRepository = $this->createMock(RepositoryInterface::class);
        $this->paymentLinkRepository = $this->createMock(PaymentLinkRepositoryInterface::class);
    }

    #[DataProvider('provideForIsAdyenPayment')]
    public function testIsAdyenPayment(
        bool $hasPaymentMethod,
        bool $hasGatewayConfig,
        array $config,
        ?string $factoryName,
        bool $expectedResult,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);

        $paymentMethod = $hasPaymentMethod ? $this->createMock(PaymentMethodInterface::class) : null;
        $gatewayConfig = $hasGatewayConfig ? $this->createMock(GatewayConfigInterface::class) : null;

        $payment
            ->expects(self::once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        if ($paymentMethod !== null) {
            $paymentMethod
                ->expects(self::once())
                ->method('getGatewayConfig')
                ->willReturn($gatewayConfig);
        }

        if ($gatewayConfig !== null) {
            $gatewayConfig
                ->expects(self::once())
                ->method('getConfig')
                ->willReturn($config);

            if (!isset($config['factory_name'])) {
                $gatewayConfig
                    ->expects(self::once())
                    ->method('getFactoryName')
                    ->willReturn($factoryName);
            }
        }

        $checker = $this->createChecker();
        $result = $checker->isAdyenPayment($payment);

        self::assertSame($expectedResult, $result);
    }

    public static function provideForIsAdyenPayment(): \Generator
    {
        yield 'payment without method' => [
            'hasPaymentMethod' => false,
            'hasGatewayConfig' => false,
            'config' => [],
            'factoryName' => null,
            'expectedResult' => false,
        ];

        yield 'payment method without gateway config' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => false,
            'config' => [],
            'factoryName' => null,
            'expectedResult' => false,
        ];

        yield 'adyen payment method with factory_name in config' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => AdyenClientProviderInterface::FACTORY_NAME],
            'factoryName' => null,
            'expectedResult' => true,
        ];

        yield 'non-adyen payment method with factory_name in config' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => 'stripe'],
            'factoryName' => null,
            'expectedResult' => false,
        ];

        yield 'adyen payment method with factory name fallback' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => AdyenClientProviderInterface::FACTORY_NAME,
            'expectedResult' => true,
        ];

        yield 'non-adyen payment method with factory name fallback' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => 'paypal',
            'expectedResult' => false,
        ];

        yield 'payment method with null factory name fallback' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => null,
            'expectedResult' => false,
        ];

        yield 'payment method with empty config and empty factory name' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => '',
            'expectedResult' => false,
        ];
    }

    #[DataProvider('provideForIsAdyenPaymentMethod')]
    public function testIsAdyenPaymentMethod(
        bool $hasGatewayConfig,
        array $config,
        ?string $factoryName,
        bool $expectedResult,
    ): void {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $hasGatewayConfig ? $this->createMock(GatewayConfigInterface::class) : null;

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        if ($gatewayConfig !== null) {
            $gatewayConfig
                ->expects(self::once())
                ->method('getConfig')
                ->willReturn($config);

            if (!isset($config['factory_name'])) {
                $gatewayConfig
                    ->expects(self::once())
                    ->method('getFactoryName')
                    ->willReturn($factoryName);
            }
        }

        $checker = $this->createChecker();
        $result = $checker->isAdyenPaymentMethod($paymentMethod);

        self::assertSame($expectedResult, $result);
    }

    public static function provideForIsAdyenPaymentMethod(): \Generator
    {
        yield 'payment method without gateway config' => [
            'hasGatewayConfig' => false,
            'config' => [],
            'factoryName' => null,
            'expectedResult' => false,
        ];

        yield 'adyen payment method with factory_name in config' => [
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => AdyenClientProviderInterface::FACTORY_NAME],
            'factoryName' => null,
            'expectedResult' => true,
        ];

        yield 'non-adyen payment method with factory_name in config' => [
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => 'stripe'],
            'factoryName' => null,
            'expectedResult' => false,
        ];

        yield 'adyen payment method with factory name fallback' => [
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => AdyenClientProviderInterface::FACTORY_NAME,
            'expectedResult' => true,
        ];

        yield 'non-adyen payment method with factory name fallback' => [
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => 'paypal',
            'expectedResult' => false,
        ];

        yield 'payment method with null factory name fallback' => [
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => null,
            'expectedResult' => false,
        ];

        yield 'payment method with empty factory name fallback' => [
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => '',
            'expectedResult' => false,
        ];

        yield 'config with factory_name prioritized over getFactoryName' => [
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => AdyenClientProviderInterface::FACTORY_NAME],
            'factoryName' => null,
            'expectedResult' => true,
        ];

        yield 'config with other keys but no factory_name uses fallback' => [
            'hasGatewayConfig' => true,
            'config' => ['some_other_key' => 'value'],
            'factoryName' => AdyenClientProviderInterface::FACTORY_NAME,
            'expectedResult' => true,
        ];
    }

    public function testIsCaptureModeWithPaymentThrowsExceptionWhenPaymentMethodIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $payment
            ->expects(self::once())
            ->method('getMethod')
            ->willReturn(null);

        $payment
            ->expects(self::atLeastOnce())
            ->method('getDetails')
            ->willReturn([]);

        $this->adyenPaymentDetailRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['payment' => $payment])
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected an instance of Sylius\Component\Core\Model\PaymentMethodInterface. Got: NULL');

        $checker = $this->createChecker();
        $checker->isCaptureMode($payment, PaymentCaptureMode::MANUAL);
    }

    #[DataProvider('provideForIsCaptureModeWithoutPaymentDetail')]
    public function testIsCaptureModeWithoutPaymentDetail(
        array $onlyManualCaptureMethods,
        array $paymentDetails,
        bool $hasPaymentMethod,
        bool $hasGatewayConfig,
        array $config,
        string $mode,
        bool $expectedResult,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $hasPaymentMethod ? $this->createMock(PaymentMethodInterface::class) : null;
        $gatewayConfig = $hasGatewayConfig ? $this->createMock(GatewayConfigInterface::class) : null;

        $this->adyenPaymentDetailRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['payment' => $payment])
            ->willReturn(null);

        $payment
            ->expects(self::atLeastOnce())
            ->method('getDetails')
            ->willReturn($paymentDetails);

        $paymentMethodType = $paymentDetails['paymentMethod']['type']
            ?? $paymentDetails['action']['paymentMethodType']
            ?? null;

        $allowsOnlyManual = [] !== $onlyManualCaptureMethods &&
            $paymentMethodType !== null &&
            in_array($paymentMethodType, $onlyManualCaptureMethods, true);

        $expectsGetMethod = !$allowsOnlyManual;

        if ($expectsGetMethod) {
            $payment
                ->expects(self::once())
                ->method('getMethod')
                ->willReturn($paymentMethod);

            if ($paymentMethod !== null) {
                $paymentMethod
                    ->expects(self::once())
                    ->method('getGatewayConfig')
                    ->willReturn($gatewayConfig);
            }

            if ($gatewayConfig !== null) {
                $gatewayConfig
                    ->expects(self::once())
                    ->method('getConfig')
                    ->willReturn($config);
            }
        } else {
            $payment
                ->expects(self::never())
                ->method('getMethod');
        }

        $checker = $this->createChecker($onlyManualCaptureMethods);
        $result = $checker->isCaptureMode($payment, $mode);

        self::assertSame($expectedResult, $result);
    }

    public static function provideForIsCaptureModeWithoutPaymentDetail(): \Generator
    {
        yield 'payment with manual capture mode configured' => [
            'onlyManualCaptureMethods' => [],
            'paymentDetails' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::MANUAL],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => true,
        ];

        yield 'payment with automatic capture mode configured' => [
            'onlyManualCaptureMethods' => [],
            'paymentDetails' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => true,
        ];

        yield 'payment with manual mode checking for automatic' => [
            'onlyManualCaptureMethods' => [],
            'paymentDetails' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::MANUAL],
            'mode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => false,
        ];

        yield 'payment with automatic mode checking for manual' => [
            'onlyManualCaptureMethods' => [],
            'paymentDetails' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment without gateway config' => [
            'onlyManualCaptureMethods' => [],
            'paymentDetails' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => false,
            'config' => [],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment with empty config' => [
            'onlyManualCaptureMethods' => [],
            'paymentDetails' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment with other config keys' => [
            'onlyManualCaptureMethods' => [],
            'paymentDetails' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['other_key' => 'value'],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];
        yield 'payment with onlyManualCaptureMethods matching paymentMethod type' => [
            'onlyManualCaptureMethods' => ['klarna', 'afterpay'],
            'paymentDetails' => ['paymentMethod' => ['type' => 'klarna']],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => true,
        ];

        yield 'payment with onlyManualCaptureMethods not matching paymentMethod type' => [
            'onlyManualCaptureMethods' => ['klarna', 'afterpay'],
            'paymentDetails' => ['paymentMethod' => ['type' => 'scheme']],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment with onlyManualCaptureMethods matching action paymentMethodType' => [
            'onlyManualCaptureMethods' => ['klarna'],
            'paymentDetails' => ['action' => ['paymentMethodType' => 'klarna']],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => true,
        ];

        yield 'payment with empty onlyManualCaptureMethods' => [
            'onlyManualCaptureMethods' => [],
            'paymentDetails' => ['paymentMethod' => ['type' => 'klarna']],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment with onlyManualCaptureMethods but no payment details' => [
            'onlyManualCaptureMethods' => ['klarna'],
            'paymentDetails' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];
    }

    #[DataProvider('provideForIsCaptureModeWithAdyenPaymentDetail')]
    public function testIsCaptureModeWithAdyenPaymentDetail(
        string $detailCaptureMode,
        string $checkMode,
        bool $expectedResult,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);
        $adyenPaymentDetail = $this->createMock(AdyenPaymentDetailInterface::class);

        $this->adyenPaymentDetailRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['payment' => $payment])
            ->willReturn($adyenPaymentDetail);

        $adyenPaymentDetail
            ->expects(self::once())
            ->method('getCaptureMode')
            ->willReturn($detailCaptureMode);

        $payment
            ->expects(self::never())
            ->method('getMethod');

        $checker = $this->createChecker();
        $result = $checker->isCaptureMode($payment, $checkMode);

        self::assertSame($expectedResult, $result);
    }

    public static function provideForIsCaptureModeWithAdyenPaymentDetail(): \Generator
    {
        yield 'payment detail with manual capture mode checking manual' => [
            'detailCaptureMode' => PaymentCaptureMode::MANUAL,
            'checkMode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => true,
        ];

        yield 'payment detail with automatic capture mode checking automatic' => [
            'detailCaptureMode' => PaymentCaptureMode::AUTOMATIC,
            'checkMode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => true,
        ];

        yield 'payment detail with manual capture mode checking automatic' => [
            'detailCaptureMode' => PaymentCaptureMode::MANUAL,
            'checkMode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => false,
        ];

        yield 'payment detail with automatic capture mode checking manual' => [
            'detailCaptureMode' => PaymentCaptureMode::AUTOMATIC,
            'checkMode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];
    }

    #[DataProvider('provideForIsCaptureModeWithPaymentMethod')]
    public function testIsCaptureModeWithPaymentMethod(
        bool $hasGatewayConfig,
        array $config,
        string $mode,
        bool $expectedResult,
    ): void {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $hasGatewayConfig ? $this->createMock(GatewayConfigInterface::class) : null;

        $this->adyenPaymentDetailRepository
            ->expects(self::never())
            ->method('findOneBy');

        $paymentMethod
            ->expects(self::once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        if ($gatewayConfig !== null) {
            $gatewayConfig
                ->expects(self::once())
                ->method('getConfig')
                ->willReturn($config);
        }

        $checker = $this->createChecker();
        $result = $checker->isCaptureMode($paymentMethod, $mode);

        self::assertSame($expectedResult, $result);
    }

    public static function provideForIsCaptureModeWithPaymentMethod(): \Generator
    {
        yield 'payment method with manual capture mode configured' => [
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::MANUAL],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => true,
        ];

        yield 'payment method with automatic capture mode configured' => [
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => true,
        ];

        yield 'payment method with manual mode checking for automatic' => [
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::MANUAL],
            'mode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => false,
        ];

        yield 'payment method with automatic mode checking for manual' => [
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment method without gateway config' => [
            'hasGatewayConfig' => false,
            'config' => [],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment method with empty config' => [
            'hasGatewayConfig' => true,
            'config' => [],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment method with other config keys' => [
            'hasGatewayConfig' => true,
            'config' => ['other_key' => 'value'],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];
    }

    #[DataProvider('provideForIsPayByLink')]
    public function testIsPayByLink(
        bool $hasPaymentMethod,
        bool $hasGatewayConfig,
        array $config,
        ?string $factoryName,
        array $paymentLinks,
        bool $shouldCallRepository,
        bool $expectedResult,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $hasPaymentMethod ? $this->createMock(PaymentMethodInterface::class) : null;
        $gatewayConfig = $hasGatewayConfig ? $this->createMock(GatewayConfigInterface::class) : null;

        $payment
            ->expects(self::once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        if ($paymentMethod !== null) {
            $paymentMethod
                ->expects(self::once())
                ->method('getGatewayConfig')
                ->willReturn($gatewayConfig);
        }

        if ($gatewayConfig !== null) {
            $gatewayConfig
                ->expects(self::once())
                ->method('getConfig')
                ->willReturn($config);

            if (!isset($config['factory_name'])) {
                $gatewayConfig
                    ->expects(self::once())
                    ->method('getFactoryName')
                    ->willReturn($factoryName);
            }
        }

        if ($shouldCallRepository) {
            $this->paymentLinkRepository
                ->expects(self::once())
                ->method('findBy')
                ->with(['payment' => $payment], null, 1, null)
                ->willReturn($paymentLinks);
        } else {
            $this->paymentLinkRepository
                ->expects(self::never())
                ->method('findBy');
        }

        $checker = $this->createChecker();
        $result = $checker->isPayByLink($payment);

        self::assertSame($expectedResult, $result);
    }

    public static function provideForIsPayByLink(): \Generator
    {
        yield 'payment without method' => [
            'hasPaymentMethod' => false,
            'hasGatewayConfig' => false,
            'config' => [],
            'factoryName' => null,
            'paymentLinks' => [],
            'shouldCallRepository' => false,
            'expectedResult' => false,
        ];

        yield 'payment method without gateway config' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => false,
            'config' => [],
            'factoryName' => null,
            'paymentLinks' => [],
            'shouldCallRepository' => false,
            'expectedResult' => false,
        ];

        yield 'non-adyen payment method with factory_name in config' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => 'stripe'],
            'factoryName' => null,
            'paymentLinks' => [],
            'shouldCallRepository' => false,
            'expectedResult' => false,
        ];

        yield 'non-adyen payment method with factory name fallback' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => 'paypal',
            'paymentLinks' => [],
            'shouldCallRepository' => false,
            'expectedResult' => false,
        ];

        yield 'adyen payment without payment links' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => AdyenClientProviderInterface::FACTORY_NAME],
            'factoryName' => null,
            'paymentLinks' => [],
            'shouldCallRepository' => true,
            'expectedResult' => false,
        ];

        yield 'adyen payment with payment links' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => AdyenClientProviderInterface::FACTORY_NAME],
            'factoryName' => null,
            'paymentLinks' => [new \stdClass()],
            'shouldCallRepository' => true,
            'expectedResult' => true,
        ];

        yield 'adyen payment with multiple payment links' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => AdyenClientProviderInterface::FACTORY_NAME,
            'paymentLinks' => [new \stdClass(), new \stdClass()],
            'shouldCallRepository' => true,
            'expectedResult' => true,
        ];
    }

    private function createChecker(array $onlyManualCaptureMethods = []): AdyenPaymentMethodChecker
    {
        return new AdyenPaymentMethodChecker(
            $this->adyenPaymentDetailRepository,
            $this->paymentLinkRepository,
            $onlyManualCaptureMethods,
        );
    }
}
