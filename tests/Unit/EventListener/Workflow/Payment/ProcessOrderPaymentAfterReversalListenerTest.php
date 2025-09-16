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
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\ProcessOrderPaymentAfterReversalListener;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\AdyenPlugin\Processor\Order\OrderPaymentProcessorInterface;
use Symfony\Component\Workflow\Event\Event;

final class ProcessOrderPaymentAfterReversalListenerTest extends TestCase
{
    private OrderPaymentProcessorInterface&MockObject $orderPaymentProcessor;
    private ProcessOrderPaymentAfterReversalListener $listener;

    protected function setUp(): void
    {
        $this->orderPaymentProcessor = $this->createMock(OrderPaymentProcessorInterface::class);
        $this->listener = new ProcessOrderPaymentAfterReversalListener($this->orderPaymentProcessor);
    }

    public function testItProcessesOrderWhenPaymentHasOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($payment);
        $payment->expects($this->once())->method('getOrder')->willReturn($order);

        $this->orderPaymentProcessor->expects($this->once())->method('process')->with($order);

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenPaymentHasNoOrder(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($payment);
        $payment->expects($this->once())->method('getOrder')->willReturn(null);

        $this->orderPaymentProcessor->expects($this->never())->method('process');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNotPaymentInterface(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(new \stdClass());

        $this->orderPaymentProcessor->expects($this->never())->method('process');

        ($this->listener)($event);
    }
}
