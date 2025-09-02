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
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodChecker;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

final class AdyenPaymentMethodCheckerTest extends TestCase
{
    private AdyenPaymentMethodChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new AdyenPaymentMethodChecker();
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
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        if ($paymentMethod !== null) {
            $paymentMethod
                ->expects($this->once())
                ->method('getGatewayConfig')
                ->willReturn($gatewayConfig);
        }

        if ($gatewayConfig !== null) {
            $gatewayConfig
                ->expects($this->once())
                ->method('getConfig')
                ->willReturn($config);

            if (!isset($config['factory_name'])) {
                $gatewayConfig
                    ->expects($this->once())
                    ->method('getFactoryName')
                    ->willReturn($factoryName);
            }
        }

        $result = $this->checker->isAdyenPayment($payment);

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
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        if ($gatewayConfig !== null) {
            $gatewayConfig
                ->expects($this->once())
                ->method('getConfig')
                ->willReturn($config);

            if (!isset($config['factory_name'])) {
                $gatewayConfig
                    ->expects($this->once())
                    ->method('getFactoryName')
                    ->willReturn($factoryName);
            }
        }

        $result = $this->checker->isAdyenPaymentMethod($paymentMethod);

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
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The payment has no payment method assigned.');

        $this->checker->isCaptureMode($payment, PaymentCaptureMode::MANUAL);
    }

    #[DataProvider('provideForIsCaptureModeWithPayment')]
    public function testIsCaptureModeWithPayment(
        bool $hasPaymentMethod,
        bool $hasGatewayConfig,
        array $config,
        string $mode,
        bool $expectedResult,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $hasPaymentMethod ? $this->createMock(PaymentMethodInterface::class) : null;
        $gatewayConfig = $hasGatewayConfig ? $this->createMock(GatewayConfigInterface::class) : null;

        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        if ($paymentMethod !== null) {
            $paymentMethod
                ->expects($this->once())
                ->method('getGatewayConfig')
                ->willReturn($gatewayConfig);
        }

        if ($gatewayConfig !== null) {
            $gatewayConfig
                ->expects($this->once())
                ->method('getConfig')
                ->willReturn($config);
        }

        $result = $this->checker->isCaptureMode($payment, $mode);

        self::assertSame($expectedResult, $result);
    }

    public static function provideForIsCaptureModeWithPayment(): \Generator
    {
        yield 'payment with manual capture mode configured' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::MANUAL],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => true,
        ];

        yield 'payment with automatic capture mode configured' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => true,
        ];

        yield 'payment with manual mode checking for automatic' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::MANUAL],
            'mode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => false,
        ];

        yield 'payment with automatic mode checking for manual' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment without gateway config' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => false,
            'config' => [],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment with empty config' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment with other config keys' => [
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['other_key' => 'value'],
            'mode' => PaymentCaptureMode::MANUAL,
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

        $paymentMethod
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        if ($gatewayConfig !== null) {
            $gatewayConfig
                ->expects($this->once())
                ->method('getConfig')
                ->willReturn($config);
        }

        $result = $this->checker->isCaptureMode($paymentMethod, $mode);

        self::assertSame($expectedResult, $result);
    }

    public static function provideForIsCaptureModeWithPaymentMethod(): \Generator
    {
        yield 'payment method with manual capture mode' => [
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::MANUAL],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => true,
        ];

        yield 'payment method with automatic capture mode' => [
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::AUTOMATIC],
            'mode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => true,
        ];

        yield 'payment method with manual checking for automatic' => [
            'hasGatewayConfig' => true,
            'config' => ['captureMode' => PaymentCaptureMode::MANUAL],
            'mode' => PaymentCaptureMode::AUTOMATIC,
            'expectedResult' => false,
        ];

        yield 'payment method without gateway config' => [
            'hasGatewayConfig' => false,
            'config' => [],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];

        yield 'payment method with no capture mode in config' => [
            'hasGatewayConfig' => true,
            'config' => [],
            'mode' => PaymentCaptureMode::MANUAL,
            'expectedResult' => false,
        ];
    }
}
