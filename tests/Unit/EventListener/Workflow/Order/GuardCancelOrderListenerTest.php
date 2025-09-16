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

namespace Tests\Sylius\AdyenPlugin\Unit\EventListener\Workflow\Order;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\EventListener\Workflow\Order\GuardCancelOrderListener;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\StateMachine\Guard\OrderGuard;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class GuardCancelOrderListenerTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface&MockObject $adyenPaymentMethodChecker;
    private OrderGuard $guard;
    private GuardCancelOrderListener $listener;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->guard = new OrderGuard($this->adyenPaymentMethodChecker);
        $this->listener = new GuardCancelOrderListener($this->guard);
    }

    public function testItBlocksEventWhenOrderCannotBeCancelled(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($order, $marking, $transition, $workflow);

        $order->expects($this->once())->method('getPayments')->willReturn(new ArrayCollection([$payment]));
        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')->with($payment)
            ->willReturn(true)
        ;
        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn(true)
        ;
        $payment->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_PROCESSING)
        ;

        ($this->listener)($event);

        $this->assertTrue($event->isBlocked());
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

        $order->expects($this->once())
            ->method('getPayments')
            ->willReturn(new ArrayCollection([]))
        ;

        ($this->listener)($event);

        $this->assertFalse($event->isBlocked());
    }
}
