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
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\AdyenPlugin\Callback\RequestCancelCallback;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\EventListener\Workflow\Order\RequestCancelOnCancelListener;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\Event;

final class RequestCancelOnCancelListenerTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface&MockObject $adyenPaymentMethodChecker;

    private MockObject&StateMachineInterface $stateMachine;

    private MessageBusInterface&MockObject $messageBus;

    private RequestCancelCallback $requestCancelCallback;

    private RequestCancelOnCancelListener $listener;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->requestCancelCallback = new RequestCancelCallback(
            $this->adyenPaymentMethodChecker,
            $this->stateMachine,
            $this->messageBus,
        );
        $this->listener = new RequestCancelOnCancelListener($this->requestCancelCallback);
    }

    public function testItCanBeConstructed(): void
    {
        $this->assertInstanceOf(RequestCancelOnCancelListener::class, $this->listener);
    }

    public function testItDispatchesCancelPaymentWhenConditionsAreMet(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($order);
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
        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS)
            ->willReturn(true)
        ;
        $payment->expects($this->once())->method('getDetails')->willReturn([]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($command) use ($order) {
                return $command instanceof CancelPayment && $command->getOrder() === $order;
            }))
            ->willReturn(new Envelope(new CancelPayment($order)))
        ;

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNotOrderInterface(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())
            ->method('getSubject')
            ->willReturn(new \stdClass())
        ;

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenOrderHasNoPayment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($order);
        $order->expects($this->once())->method('getLastPayment')->willReturn(null);

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenPaymentIsNotAdyenPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($order);
        $order->expects($this->once())->method('getLastPayment')->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false)
        ;

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenPaymentIsAutomaticCaptureMode(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($order);
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
            ->willReturn(true);

        $this->messageBus->expects($this->never())->method('dispatch');

        ($this->listener)($event);
    }
}
