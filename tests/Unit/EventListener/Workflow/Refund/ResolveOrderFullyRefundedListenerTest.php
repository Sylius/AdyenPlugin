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
use Sylius\AdyenPlugin\EventListener\Workflow\Refund\ResolveOrderFullyRefundedListener;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\RefundPlugin\StateResolver\OrderFullyRefundedStateResolverInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Symfony\Component\Workflow\Event\Event;

final class ResolveOrderFullyRefundedListenerTest extends TestCase
{
    private OrderFullyRefundedStateResolverInterface&MockObject $resolver;
    private ResolveOrderFullyRefundedListener $listener;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(OrderFullyRefundedStateResolverInterface::class);
        $this->listener = new ResolveOrderFullyRefundedListener($this->resolver);
    }

    public function testItResolvesFullyRefundedStateWhenSubjectIsRefundPayment(): void
    {
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $event = $this->createMock(Event::class);
        $order = $this->createMock(OrderInterface::class);

        $event->expects($this->once())->method('getSubject')->willReturn($refundPayment);
        $refundPayment->expects($this->once())->method('getOrder')->willReturn($order);
        $order->expects($this->once())->method('getNumber')->willReturn('ORDER-123');

        $this->resolver->expects($this->once())->method('resolve')->with('ORDER-123');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNotRefundPaymentInterface(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(new \stdClass());

        $this->resolver->expects($this->never())->method('resolve');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNull(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(null);

        $this->resolver->expects($this->never())->method('resolve');

        ($this->listener)($event);
    }
}
