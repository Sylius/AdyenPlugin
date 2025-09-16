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
use Sylius\AdyenPlugin\EventListener\Workflow\Refund\ProcessRefundPaymentOnConfirmListener;
use Sylius\AdyenPlugin\Processor\Refund\RefundPaymentStateProcessorInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Symfony\Component\Workflow\Event\Event;

final class ProcessRefundPaymentOnConfirmListenerTest extends TestCase
{
    private RefundPaymentStateProcessorInterface&MockObject $processor;
    private ProcessRefundPaymentOnConfirmListener $listener;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(RefundPaymentStateProcessorInterface::class);
        $this->listener = new ProcessRefundPaymentOnConfirmListener($this->processor);
    }

    public function testItProcessesRefundPaymentWhenSubjectIsRefundPaymentInterface(): void
    {
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($refundPayment);

        $this->processor->expects($this->once())->method('process')->with($refundPayment);

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNotRefundPaymentInterface(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(new \stdClass());

        $this->processor->expects($this->never())->method('process');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNull(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(null);

        $this->processor->expects($this->never())->method('process');

        ($this->listener)($event);
    }
}
