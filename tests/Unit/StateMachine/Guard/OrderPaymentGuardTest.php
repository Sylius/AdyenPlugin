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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\StateMachine\Guard\OrderPaymentGuard;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OrderPaymentGuardTest extends TestCase
{
    private OrderPaymentGuard $guard;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->guard = new OrderPaymentGuard($this->adyenPaymentMethodChecker);
    }

    public function testItAllowsCancellationForAdyenPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_NEW);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false);

        self::assertTrue($this->guard->canBeCancelled($order));
    }

    public function testItDeniesCancellationForAdyenPaymentInProcessingReversalState(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentGraph::STATE_PROCESSING_REVERSAL);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true);

        self::assertFalse($this->guard->canBeCancelled($order));
    }

    public function testItAllowsCancellationForNonAdyenPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        self::assertTrue($this->guard->canBeCancelled($order));
    }

    public function testItAllowsCancellationWhenPaymentIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn(null);

        self::assertTrue($this->guard->canBeCancelled($order));
    }

    public function testItAllowsCancellationWhenPaymentMethodIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        self::assertTrue($this->guard->canBeCancelled($order));
    }

    public function testItAllowsRequestPaymentForNonAdyenPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        self::assertTrue($this->guard->canRequestPayment($order));
    }

    public function testItAllowsRequestPaymentWhenPaymentIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn(null);

        self::assertTrue($this->guard->canRequestPayment($order));
    }

    public function testItAllowsRequestPaymentForAdyenPaymentNotInProcessingState(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_NEW);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        self::assertTrue($this->guard->canRequestPayment($order));
    }

    public function testItDeniesRequestPaymentForAdyenPaymentInProcessingState(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_PROCESSING);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        self::assertFalse($this->guard->canRequestPayment($order));
    }
}
