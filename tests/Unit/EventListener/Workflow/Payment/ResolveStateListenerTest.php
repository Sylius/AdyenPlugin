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
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\ResolveStateListener;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Symfony\Component\Workflow\Event\Event;

final class ResolveStateListenerTest extends TestCase
{
    private StateResolverInterface&MockObject $resolver;
    private ResolveStateListener $listener;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(StateResolverInterface::class);
        $this->listener = new ResolveStateListener($this->resolver);
    }

    public function testItResolvesOrderStateWhenPaymentHasOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($payment);
        $payment->expects($this->once())->method('getOrder')->willReturn($order);

        $this->resolver->expects($this->once())->method('resolve')->with($order);

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenPaymentHasNoOrder(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($payment);
        $payment->expects($this->once())->method('getOrder')->willReturn(null);

        $this->resolver->expects($this->never())->method('resolve');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNotPaymentInterface(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(new \stdClass());

        $this->resolver->expects($this->never())->method('resolve');

        ($this->listener)($event);
    }
}
