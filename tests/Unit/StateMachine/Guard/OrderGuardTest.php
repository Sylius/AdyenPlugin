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

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\StateMachine\Guard\OrderGuard;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OrderGuardTest extends TestCase
{
    private OrderGuard $guard;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->guard = new OrderGuard($this->adyenPaymentMethodChecker);
    }

    public function testCanBeCancelledReturnsTrueWhenOrderHasNoPayments(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects(self::once())
            ->method('getPayments')
            ->willReturn(new ArrayCollection([]));

        $this->adyenPaymentMethodChecker
            ->expects(self::never())
            ->method('isAdyenPayment');

        $result = $this->guard->canBeCancelled($order);

        self::assertTrue($result);
    }

    public function testCanBeCancelledReturnsTrueWhenOrderHasNonAdyenPayments(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects(self::once())
            ->method('getPayments')
            ->willReturn(new ArrayCollection([$payment]));

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        $this->adyenPaymentMethodChecker
            ->expects(self::never())
            ->method('isCaptureMode');

        $result = $this->guard->canBeCancelled($order);

        self::assertTrue($result);
    }

    public function testCanBeCancelledReturnsTrueWhenAdyenPaymentHasAutomaticCapture(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects(self::once())
            ->method('getPayments')
            ->willReturn(new ArrayCollection([$payment]));

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn(false);

        $payment
            ->expects(self::never())
            ->method('getState');

        $result = $this->guard->canBeCancelled($order);

        self::assertTrue($result);
    }

    public function testCanBeCancelledReturnsTrueWhenAdyenManualPaymentIsNotProcessing(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects(self::once())
            ->method('getPayments')
            ->willReturn(new ArrayCollection([$payment]));

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn(true);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);

        $result = $this->guard->canBeCancelled($order);

        self::assertTrue($result);
    }

    public function testCanBeCancelledReturnsFalseWhenAdyenManualPaymentIsProcessing(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects(self::once())
            ->method('getPayments')
            ->willReturn(new ArrayCollection([$payment]));

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn(true);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_PROCESSING);

        $result = $this->guard->canBeCancelled($order);

        self::assertFalse($result);
    }

    public function testCanBeCancelledWithMultiplePayments(): void
    {
        $payment1 = $this->createMock(PaymentInterface::class);
        $payment2 = $this->createMock(PaymentInterface::class);
        $payment3 = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects(self::once())
            ->method('getPayments')
            ->willReturn(new ArrayCollection([$payment1, $payment2, $payment3]));

        $this->adyenPaymentMethodChecker
            ->expects(self::exactly(3))
            ->method('isAdyenPayment')
            ->willReturnCallback(function ($payment) use ($payment1, $payment2, $payment3) {
                if ($payment === $payment1) {
                    return false;
                }
                if ($payment === $payment2) {
                    return true;
                }
                if ($payment === $payment3) {
                    return true;
                }

                return false;
            });

        $this->adyenPaymentMethodChecker
            ->expects(self::exactly(2))
            ->method('isCaptureMode')
            ->willReturnCallback(function ($payment, $mode) use ($payment2, $payment3) {
                if ($payment === $payment2 && $mode === PaymentCaptureMode::MANUAL) {
                    return false;
                }
                if ($payment === $payment3 && $mode === PaymentCaptureMode::MANUAL) {
                    return true;
                }

                return false;
            });

        $payment3
            ->expects(self::once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_AUTHORIZED);

        $result = $this->guard->canBeCancelled($order);

        self::assertTrue($result);
    }

    public function testCanBeCancelledReturnsFalseWhenOnePaymentBlocksCancellation(): void
    {
        $payment1 = $this->createMock(PaymentInterface::class);
        $payment2 = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects(self::once())
            ->method('getPayments')
            ->willReturn(new ArrayCollection([$payment1, $payment2]));

        $this->adyenPaymentMethodChecker
            ->expects(self::exactly(2))
            ->method('isAdyenPayment')
            ->willReturnCallback(function ($payment) use ($payment1, $payment2) {
                if ($payment === $payment1) {
                    return false;
                }
                if ($payment === $payment2) {
                    return true;
                }

                return false;
            });

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isCaptureMode')
            ->with($payment2, PaymentCaptureMode::MANUAL)
            ->willReturn(true);

        $payment2
            ->expects(self::once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_PROCESSING);

        $result = $this->guard->canBeCancelled($order);

        self::assertFalse($result);
    }

    public function testCanBeCancelledWithVariousPaymentStates(): void
    {
        $states = [
            PaymentInterface::STATE_NEW,
            PaymentInterface::STATE_AUTHORIZED,
            PaymentInterface::STATE_COMPLETED,
            PaymentInterface::STATE_FAILED,
            PaymentInterface::STATE_CANCELLED,
            PaymentInterface::STATE_REFUNDED,
        ];

        foreach ($states as $state) {
            $payment = $this->createMock(PaymentInterface::class);
            $order = $this->createMock(OrderInterface::class);
            $order
                ->expects(self::once())
                ->method('getPayments')
                ->willReturn(new ArrayCollection([$payment]));

            $this->adyenPaymentMethodChecker
                ->expects(self::once())
                ->method('isAdyenPayment')
                ->with($payment)
                ->willReturn(true);

            $this->adyenPaymentMethodChecker
                ->expects(self::once())
                ->method('isCaptureMode')
                ->with($payment, PaymentCaptureMode::MANUAL)
                ->willReturn(true);

            $payment
                ->expects(self::once())
                ->method('getState')
                ->willReturn($state);

            $result = $this->guard->canBeCancelled($order);

            // Only STATE_PROCESSING should return false, all others should return true
            if ($state === PaymentInterface::STATE_PROCESSING) {
                self::assertFalse($result, "State $state should block cancellation");
            } else {
                self::assertTrue($result, "State $state should allow cancellation");
            }

            $this->setUp();
        }
    }
}
