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

namespace Tests\Sylius\AdyenPlugin\Unit\EventListener\Workflow\Payment;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\GuardCancelListener;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\StateMachine\Guard\AdyenPaymentGuard;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class GuardCancelListenerTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface&MockObject $adyenPaymentMethodChecker;
    private AdyenPaymentGuard $guard;
    private GuardCancelListener $listener;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->guard = new AdyenPaymentGuard($this->adyenPaymentMethodChecker);
        $this->listener = new GuardCancelListener($this->guard);
    }

    public function testItCanBeConstructed(): void
    {
        $this->assertInstanceOf(GuardCancelListener::class, $this->listener);
    }

    public function testItDoesNothingWhenSubjectIsNotPaymentInterface(): void
    {
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent(new \stdClass(), $marking, $transition, $workflow);

        ($this->listener)($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testItDoesNothingWhenPaymentIsNotAdyenPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($payment, $marking, $transition, $workflow);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false)
        ;

        ($this->listener)($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testItDoesNothingWhenPaymentIsAutomaticCapture(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($payment, $marking, $transition, $workflow);

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

        ($this->listener)($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testItDoesNothingWhenProcessingPaymentHasCancellationFlag(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($payment, $marking, $transition, $workflow);

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

        $payment
            ->expects($this->once())
            ->method('getDetails')
            ->willReturn([CancelPayment::PROCESSING_CANCELLATION => true])
        ;

        ($this->listener)($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testItBlocksEventWhenManualCapturePaymentCannotBeCancelled(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($payment, $marking, $transition, $workflow);

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
            ->willReturn(PaymentInterface::STATE_NEW)
        ;

        ($this->listener)($event);

        $this->assertTrue($event->isBlocked());
    }
}