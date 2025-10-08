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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\EventListener\Workflow\Order\ReversePaymentOnCancelListener;
use Sylius\AdyenPlugin\Processor\Order\OrderPaymentProcessorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\Event;

final class ReversePaymentOnCancelListenerTest extends TestCase
{
    private MockObject&OrderPaymentProcessorInterface $processor;

    private ReversePaymentOnCancelListener $listener;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(OrderPaymentProcessorInterface::class);
        $this->listener = new ReversePaymentOnCancelListener($this->processor);
    }

    public function testItProcessesOrderWhenSubjectIsOrderInterface(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($order);

        $this->processor->expects($this->once())->method('process')->with($order);

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNotOrderInterface(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(new \stdClass());

        $this->processor->expects($this->never())->method('process');

        ($this->listener)($event);
    }
}
