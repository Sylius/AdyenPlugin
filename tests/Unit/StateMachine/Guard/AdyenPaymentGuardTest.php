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

namespace Tests\Sylius\AdyenPlugin\Unit\StateMachine\Guard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\StateMachine\Guard\AdyenPaymentGuard;
use Sylius\Component\Core\Model\PaymentInterface;

final class AdyenPaymentGuardTest extends TestCase
{
    private AdyenPaymentGuard $guard;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->guard = new AdyenPaymentGuard($this->adyenPaymentMethodChecker);
    }

    #[DataProvider('canBeCompletedProvider')]
    public function testCanBeCompleted(
        bool $isAdyenPayment,
        bool $isManualCapture,
        int $isAdyenPaymentCalls,
        int $isCaptureModeCalls,
        bool $expectedResult,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);

        if ($isAdyenPaymentCalls > 0) {
            $this->adyenPaymentMethodChecker
                ->expects($this->exactly($isAdyenPaymentCalls))
                ->method('isAdyenPayment')
                ->with($payment)
                ->willReturn($isAdyenPayment);
        }

        if ($isCaptureModeCalls > 0) {
            $this->adyenPaymentMethodChecker
                ->expects($this->exactly($isCaptureModeCalls))
                ->method('isCaptureMode')
                ->with($payment, PaymentCaptureMode::MANUAL)
                ->willReturn($isManualCapture);
        }

        $result = $this->guard->canBeCompleted($payment);

        self::assertSame($expectedResult, $result);
    }

    public static function canBeCompletedProvider(): \Generator
    {
        yield 'non-Adyen payment can be completed' => [
            'isAdyenPayment' => false,
            'isManualCapture' => false,
            'isAdyenPaymentCalls' => 1,
            'isCaptureModeCalls' => 0,
            'expectedResult' => true,
        ];

        yield 'Adyen payment with manual capture can be completed' => [
            'isAdyenPayment' => true,
            'isManualCapture' => true,
            'isAdyenPaymentCalls' => 1,
            'isCaptureModeCalls' => 1,
            'expectedResult' => true,
        ];

        yield 'Adyen payment with automatic capture cannot be completed' => [
            'isAdyenPayment' => true,
            'isManualCapture' => false,
            'isAdyenPaymentCalls' => 1,
            'isCaptureModeCalls' => 1,
            'expectedResult' => false,
        ];
    }

    public function testCanBeCompletedForNonAdyenPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        $this->adyenPaymentMethodChecker
            ->expects($this->never())
            ->method('isCaptureMode');

        self::assertTrue($this->guard->canBeCompleted($payment));
    }

    public function testCanBeCompletedForAdyenPaymentWithManualCapture(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn(true);

        self::assertTrue($this->guard->canBeCompleted($payment));
    }

    public function testCanBeCompletedForAdyenPaymentWithAutomaticCapture(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn(false);

        self::assertFalse($this->guard->canBeCompleted($payment));
    }
}