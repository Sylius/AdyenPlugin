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

namespace Tests\Sylius\AdyenPlugin\Unit\Processor\Order;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\ReversePayment;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Processor\Order\ReverseOrderPaymentProcessor;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReverseOrderPaymentProcessorTest extends TestCase
{
    private ReverseOrderPaymentProcessor $processor;

    private MessageBusInterface|MockObject $commandBus;

    private MockObject|StateMachineInterface $stateMachine;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->processor = new ReverseOrderPaymentProcessor(
            $this->commandBus,
            $this->stateMachine,
            $this->adyenPaymentMethodChecker,
        );
    }

    public function testDoesNothingWhenOrderIsNull(): void
    {
        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process(null);
    }

    public function testDispatchesReversePaymentCommandForCompletedAdyenPayment(): void
    {
        $completedPayment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($completedPayment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($completedPayment)
            ->willReturn(true);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ReversePayment::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testAppliesCancelTransitionWhenNoCompletedPaymentAndCanTransition(): void
    {
        $lastPayment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->exactly(2))
            ->method('getLastPayment')
            ->willReturnCallback(function ($state = null) use ($lastPayment) {
                if ($state === PaymentInterface::STATE_COMPLETED) {
                    return null;
                }

                return $lastPayment;
            });

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($lastPayment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CANCEL)
            ->willReturn(true);

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($lastPayment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CANCEL);

        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        $this->processor->process($order);
    }

    public function testDoesNotApplyCancelTransitionWhenCannotTransition(): void
    {
        $lastPayment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->exactly(2))
            ->method('getLastPayment')
            ->willReturnCallback(function ($state = null) use ($lastPayment) {
                if ($state === PaymentInterface::STATE_COMPLETED) {
                    return null;
                }

                return $lastPayment;
            });

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($lastPayment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CANCEL)
            ->willReturn(false);

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        $this->processor->process($order);
    }

    public function testDoesNothingWhenNoPaymentsExist(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->exactly(2))
            ->method('getLastPayment')
            ->willReturnCallback(function ($state = null) {
                return null;
            });

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        $this->processor->process($order);
    }

    public function testDoesNotDispatchReversePaymentForNonAdyenCompletedPayment(): void
    {
        $completedPayment = $this->createMock(PaymentInterface::class);
        $lastPayment = $this->createMock(PaymentInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->exactly(2))
            ->method('getLastPayment')
            ->willReturnCallback(function ($state = null) use ($completedPayment, $lastPayment) {
                if ($state === PaymentInterface::STATE_COMPLETED) {
                    return $completedPayment;
                }

                return $lastPayment;
            });

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($completedPayment)
            ->willReturn(false);

        $this->commandBus
            ->expects($this->never())
            ->method('dispatch');

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($lastPayment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CANCEL)
            ->willReturn(true);

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($lastPayment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CANCEL);

        $this->processor->process($order);
    }
}
