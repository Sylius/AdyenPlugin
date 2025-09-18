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

namespace Tests\Sylius\AdyenPlugin\Unit\EventListener\Workflow\OrderPayment;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\EventListener\Workflow\OrderPayment\GuardCancelListener;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\StateMachine\Guard\OrderPaymentGuard;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class GuardCancelListenerTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface&MockObject $adyenPaymentMethodChecker;

    private OrderPaymentGuard $guard;

    private GuardCancelListener $listener;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->guard = new OrderPaymentGuard($this->adyenPaymentMethodChecker);
        $this->listener = new GuardCancelListener($this->guard);
    }

    public function testItDoesNothingWhenSubjectIsNotOrderInterface(): void
    {
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent(new \stdClass(), $marking, $transition, $workflow);

        ($this->listener)($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testItDoesNothingWhenOrderCanBeCancelled(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($order, $marking, $transition, $workflow);

        $order->expects($this->once())->method('getLastPayment')->willReturn(null);

        ($this->listener)($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testItBlocksEventWhenAutomaticCapturePaymentIsInProcessingReversalState(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($order, $marking, $transition, $workflow);

        $order->expects($this->once())->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true)
        ;
        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true)
        ;
        $payment
            ->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentGraph::STATE_PROCESSING_REVERSAL)
        ;

        ($this->listener)($event);

        $this->assertTrue($event->isBlocked());
    }

    public function testItBlocksEventWhenManualCapturePaymentIsInProcessingState(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($order, $marking, $transition, $workflow);

        $order->expects($this->once())->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true)
        ;
        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false)
        ;
        $payment
            ->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_PROCESSING)
        ;

        ($this->listener)($event);

        $this->assertTrue($event->isBlocked());
    }

    public function testItBlocksEventWhenManualCapturePaymentIsInCompletedState(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($order, $marking, $transition, $workflow);

        $order->expects($this->once())->method('getLastPayment')->willReturn($payment);
        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true)
        ;
        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false)
        ;
        $payment
            ->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_COMPLETED)
        ;

        ($this->listener)($event);

        $this->assertTrue($event->isBlocked());
    }
}
