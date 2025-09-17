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
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\AdyenPlugin\Factory\AdyenPaymentDetailFactory;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class AdyenPaymentDetailFactoryTest extends TestCase
{
    private FactoryInterface|MockObject $innerFactory;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private AdyenPaymentDetailFactory $factory;

    protected function setUp(): void
    {
        $this->innerFactory = $this->createMock(FactoryInterface::class);
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);

        $this->factory = new AdyenPaymentDetailFactory($this->innerFactory, $this->adyenPaymentMethodChecker);
    }

    public function testCreateNew(): void
    {
        $paymentDetail = $this->createMock(AdyenPaymentDetailInterface::class);

        $this->innerFactory
            ->expects(self::once())
            ->method('createNew')
            ->willReturn($paymentDetail);

        $result = $this->factory->createNew();

        self::assertSame($paymentDetail, $result);
    }

    #[DataProvider('createForPaymentProvider')]
    public function testCreateForPayment(
        bool $isManualCaptureMode,
        string $expectedCaptureMode,
    ): void {
        $paymentDetail = $this->createMock(AdyenPaymentDetailInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $this->innerFactory
            ->expects(self::once())
            ->method('createNew')
            ->willReturn($paymentDetail);

        $payment
            ->expects(self::once())
            ->method('getAmount')
            ->willReturn(1000);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn($isManualCaptureMode);

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

        $result = $this->factory->createForPayment($payment);

        self::assertSame($paymentDetail, $result);
    }

    public static function createForPaymentProvider(): iterable
    {
        yield 'automatic capture mode' => [
            'isManualCaptureMode' => false,
            'expectedCaptureMode' => PaymentCaptureMode::AUTOMATIC,
        ];

        yield 'manual capture mode' => [
            'isManualCaptureMode' => true,
            'expectedCaptureMode' => PaymentCaptureMode::MANUAL,
        ];
    }
}
