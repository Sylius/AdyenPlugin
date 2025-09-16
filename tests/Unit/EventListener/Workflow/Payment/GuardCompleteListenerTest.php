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
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\GuardCompleteListener;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\StateMachine\Guard\AdyenPaymentGuard;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class GuardCompleteListenerTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface&MockObject $adyenPaymentMethodChecker;
    private AdyenPaymentGuard $guard;
    private GuardCompleteListener $listener;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->guard = new AdyenPaymentGuard($this->adyenPaymentMethodChecker);
        $this->listener = new GuardCompleteListener($this->guard);
    }

    public function testItCanBeConstructed(): void
    {
        $this->assertInstanceOf(GuardCompleteListener::class, $this->listener);
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

    public function testItDoesNothingWhenAdyenPaymentIsInManualCaptureMode(): void
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
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn(true)
        ;

        ($this->listener)($event);

        $this->assertFalse($event->isBlocked());
    }

    public function testItBlocksEventWhenAdyenPaymentIsInAutomaticCaptureMode(): void
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
            ->with($payment, PaymentCaptureMode::MANUAL)
            ->willReturn(false)
        ;

        ($this->listener)($event);

        $this->assertTrue($event->isBlocked());
    }
}