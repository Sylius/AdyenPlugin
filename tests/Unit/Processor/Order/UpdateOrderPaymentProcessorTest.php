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

namespace Tests\Sylius\AdyenPlugin\Unit\Processor\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Processor\Order\UpdateOrderPaymentStateProcessor;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderPaymentTransitions;

final class UpdateOrderPaymentProcessorTest extends TestCase
{
    private UpdateOrderPaymentStateProcessor $processor;

    private MockObject|StateMachineInterface $stateMachine;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->processor = new UpdateOrderPaymentStateProcessor(
            $this->stateMachine,
            $this->adyenPaymentMethodChecker,
        );
    }

    public function testDoesNothingWhenOrderIsNull(): void
    {
        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process(null);
    }

    public function testDoesNothingWhenOrderPaymentStateIsNotPaid(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true);

        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testDoesNothingWhenPaymentIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn(null);

        $order
            ->expects($this->never())
            ->method('getPaymentState');

        $this->adyenPaymentMethodChecker
            ->expects($this->never())
            ->method('isAdyenPayment');

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testDoesNothingWhenPaymentIsNotAdyen(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        $order
            ->expects($this->never())
            ->method('getPaymentState');

        $this->adyenPaymentMethodChecker
            ->expects($this->never())
            ->method('isCaptureMode');

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testAppliesCancelTransitionWhenCanTransition(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        // Mock both capture mode checks - automatic returns false, manual returns true
        $captureModeCallCount = 0;
        $this->adyenPaymentMethodChecker
            ->expects($this->exactly(2))
            ->method('isCaptureMode')
            ->willReturnCallback(function ($p, $mode) use ($payment, &$captureModeCallCount) {
                self::assertSame($payment, $p);
                ++$captureModeCallCount;
                if ($captureModeCallCount === 1) {
                    self::assertSame(PaymentCaptureMode::AUTOMATIC, $mode);

                    return false; // Not automatic
                }
                self::assertSame(PaymentCaptureMode::MANUAL, $mode);

                return true; // It's manual
            });

        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->willReturnCallback(function ($arg1, $arg2, $arg3) use ($order) {
                $this->assertSame($order, $arg1);
                $this->assertSame(OrderPaymentTransitions::GRAPH, $arg2);
                $this->assertContains($arg3, [OrderPaymentTransitions::TRANSITION_CANCEL, 'cancel_adyen']);

                return true;
            });

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->willReturnCallback(function ($arg1, $arg2, $arg3) use ($order) {
                $this->assertSame($order, $arg1);
                $this->assertSame(OrderPaymentTransitions::GRAPH, $arg2);
                $this->assertContains($arg3, [OrderPaymentTransitions::TRANSITION_CANCEL, 'cancel_adyen']);
            });

        $this->processor->process($order);
    }

    public function testDoesNotApplyCancelTransitionWhenCannotTransition(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        // Mock both capture mode checks - automatic returns false, manual returns true
        $captureModeCallCount = 0;
        $this->adyenPaymentMethodChecker
            ->expects($this->exactly(2))
            ->method('isCaptureMode')
            ->willReturnCallback(function ($p, $mode) use ($payment, &$captureModeCallCount) {
                self::assertSame($payment, $p);
                ++$captureModeCallCount;
                if ($captureModeCallCount === 1) {
                    self::assertSame(PaymentCaptureMode::AUTOMATIC, $mode);

                    return false; // Not automatic
                }
                self::assertSame(PaymentCaptureMode::MANUAL, $mode);

                return true; // It's manual
            });

        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $matcher = $this->exactly(4);
        $this->stateMachine
            ->expects($matcher)
            ->method('can')
            ->willReturnCallback(function ($arg1, $arg2, $arg3) use ($matcher, $order) {
                $this->assertSame($order, $arg1);
                $this->assertSame(OrderPaymentTransitions::GRAPH, $arg2);

                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame('cancel_adyen', $arg3),
                    2 => $this->assertSame(OrderPaymentTransitions::TRANSITION_CANCEL, $arg3),
                    3 => $this->assertSame('refund_adyen', $arg3),
                    4 => $this->assertSame(OrderPaymentTransitions::TRANSITION_REFUND, $arg3),
                };

                return false;
            });

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }
}
