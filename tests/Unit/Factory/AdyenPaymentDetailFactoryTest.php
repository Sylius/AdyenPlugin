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

namespace Tests\Sylius\AdyenPlugin\Unit\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\AdyenPlugin\Factory\AdyenPaymentDetailFactory;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class AdyenPaymentDetailFactoryTest extends TestCase
{
    private FactoryInterface|MockObject $innerFactory;

    protected function setUp(): void
    {
        $this->innerFactory = $this->createMock(FactoryInterface::class);
    }

    public function testCreateNew(): void
    {
        $factory = new AdyenPaymentDetailFactory($this->innerFactory);
        $paymentDetail = $this->createMock(AdyenPaymentDetailInterface::class);

        $this->innerFactory
            ->expects(self::once())
            ->method('createNew')
            ->willReturn($paymentDetail);

        $result = $factory->createNew();

        self::assertSame($paymentDetail, $result);
    }

    #[DataProvider('createForPaymentProvider')]
    public function testCreateForPayment(
        array $onlyManualCaptureMethods,
        bool $hasPaymentMethod,
        bool $hasGatewayConfig,
        array $gatewayConfigData,
        string $expectedCaptureMode,
    ): void {
        $factory = new AdyenPaymentDetailFactory($this->innerFactory, $onlyManualCaptureMethods);
        $paymentDetail = $this->createMock(AdyenPaymentDetailInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $paymentMethod = null;

        if ($hasPaymentMethod) {
            $paymentMethod = $this->createMock(PaymentMethodInterface::class);

            if ($hasGatewayConfig) {
                $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
                $gatewayConfig
                    ->expects(self::once())
                    ->method('getConfig')
                    ->willReturn($gatewayConfigData);
            }

            $paymentMethod
                ->expects(self::once())
                ->method('getGatewayConfig')
                ->willReturn($gatewayConfig ?? null);
        }

        $this->innerFactory
            ->expects(self::once())
            ->method('createNew')
            ->willReturn($paymentDetail);

        $payment
            ->expects(self::once())
            ->method('getAmount')
            ->willReturn(1000);

        $payment
            ->expects(self::once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentDetail
            ->expects(self::once())
            ->method('setPayment')
            ->with($payment);

        $paymentDetail
            ->expects(self::once())
            ->method('setAmount')
            ->with(1000);

        $paymentDetail
            ->expects(self::once())
            ->method('setCaptureMode')
            ->with($expectedCaptureMode);

        $result = $factory->createForPayment($payment);

        self::assertSame($paymentDetail, $result);
    }

    public static function createForPaymentProvider(): iterable
    {
        yield 'automatic capture mode from config' => [
            'onlyManualCaptureMethods' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'gatewayConfigData' => [
                'captureMode' => PaymentCaptureMode::AUTOMATIC,
                'paymentMethod' => ['type' => 'scheme'],
            ],
            'expectedCaptureMode' => PaymentCaptureMode::AUTOMATIC,
        ];

        yield 'manual capture mode from config' => [
            'onlyManualCaptureMethods' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'gatewayConfigData' => [
                'captureMode' => PaymentCaptureMode::MANUAL,
                'paymentMethod' => ['type' => 'scheme'],
            ],
            'expectedCaptureMode' => PaymentCaptureMode::MANUAL,
        ];

        yield 'manual capture mode from onlyManualCaptureMethods' => [
            'onlyManualCaptureMethods' => ['klarna', 'afterpay'],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'gatewayConfigData' => [
                'captureMode' => PaymentCaptureMode::AUTOMATIC,
                'paymentMethod' => ['type' => 'klarna'],
            ],
            'expectedCaptureMode' => PaymentCaptureMode::MANUAL,
        ];

        yield 'onlyManualCaptureMethods not matching' => [
            'onlyManualCaptureMethods' => ['klarna', 'afterpay'],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'gatewayConfigData' => [
                'captureMode' => PaymentCaptureMode::AUTOMATIC,
                'paymentMethod' => ['type' => 'scheme'],
            ],
            'expectedCaptureMode' => PaymentCaptureMode::AUTOMATIC,
        ];

        yield 'null payment method' => [
            'onlyManualCaptureMethods' => [],
            'hasPaymentMethod' => false,
            'hasGatewayConfig' => false,
            'gatewayConfigData' => [],
            'expectedCaptureMode' => PaymentCaptureMode::AUTOMATIC,
        ];

        yield 'null gateway config' => [
            'onlyManualCaptureMethods' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => false,
            'gatewayConfigData' => [],
            'expectedCaptureMode' => PaymentCaptureMode::AUTOMATIC,
        ];

        yield 'empty gateway config' => [
            'onlyManualCaptureMethods' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'gatewayConfigData' => [],
            'expectedCaptureMode' => PaymentCaptureMode::AUTOMATIC,
        ];

        yield 'missing capture mode in config' => [
            'onlyManualCaptureMethods' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'gatewayConfigData' => [
                'paymentMethod' => ['type' => 'scheme'],
            ],
            'expectedCaptureMode' => PaymentCaptureMode::AUTOMATIC,
        ];

        yield 'missing payment method type with onlyManualCaptureMethods' => [
            'onlyManualCaptureMethods' => ['klarna'],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'gatewayConfigData' => [
                'captureMode' => PaymentCaptureMode::AUTOMATIC,
            ],
            'expectedCaptureMode' => PaymentCaptureMode::AUTOMATIC,
        ];

        yield 'empty onlyManualCaptureMethods with manual config' => [
            'onlyManualCaptureMethods' => [],
            'hasPaymentMethod' => true,
            'hasGatewayConfig' => true,
            'gatewayConfigData' => [
                'captureMode' => PaymentCaptureMode::MANUAL,
                'paymentMethod' => ['type' => 'scheme'],
            ],
            'expectedCaptureMode' => PaymentCaptureMode::MANUAL,
        ];
    }
}
