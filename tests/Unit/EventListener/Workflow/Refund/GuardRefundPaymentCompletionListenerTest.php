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

namespace Tests\Sylius\AdyenPlugin\Unit\EventListener\Workflow\Refund;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\Refund\RefundPaymentCompletionEligibilityCheckerInterface;
use Sylius\AdyenPlugin\EventListener\Workflow\Refund\GuardRefundPaymentCompletionListener;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

final class GuardRefundPaymentCompletionListenerTest extends TestCase
{
    private MockObject&RefundPaymentCompletionEligibilityCheckerInterface $completionEligibilityChecker;

    private GuardRefundPaymentCompletionListener $listener;

    protected function setUp(): void
    {
        $this->completionEligibilityChecker = $this->createMock(RefundPaymentCompletionEligibilityCheckerInterface::class);
        $this->listener = new GuardRefundPaymentCompletionListener($this->completionEligibilityChecker);
    }

    public function testItDoesNothingWhenSubjectIsNotRefundPaymentInterface(): void
    {
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent(new \stdClass(), $marking, $transition, $workflow);

        ($this->listener)($event);

        self::assertFalse($event->isBlocked());
    }

    public function testItDoesNothingWhenRefundPaymentCanBeCompleted(): void
    {
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($refundPayment, $marking, $transition, $workflow);

        $this->completionEligibilityChecker
            ->expects(self::once())
            ->method('canBeCompleted')
            ->with($refundPayment)
            ->willReturn(true)
        ;

        ($this->listener)($event);

        self::assertFalse($event->isBlocked());
    }

    public function testItBlocksEventWhenRefundPaymentCannotBeCompleted(): void
    {
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $transition = $this->createMock(Transition::class);
        $marking = $this->createMock(Marking::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $event = new GuardEvent($refundPayment, $marking, $transition, $workflow);

        $this->completionEligibilityChecker
            ->expects(self::once())
            ->method('canBeCompleted')
            ->with($refundPayment)
            ->willReturn(false)
        ;

        ($this->listener)($event);

        self::assertTrue($event->isBlocked());
    }
}
