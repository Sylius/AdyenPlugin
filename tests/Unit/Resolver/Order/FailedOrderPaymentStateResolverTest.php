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

namespace Tests\Sylius\AdyenPlugin\Unit\Resolver\Order;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Resolver\Order\FailedOrderPaymentStateResolver;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentTransitions;

final class FailedOrderPaymentStateResolverTest extends TestCase
{
    private FailedOrderPaymentStateResolver $resolver;

    private MockObject|StateMachineInterface $stateMachine;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);

        $this->resolver = new FailedOrderPaymentStateResolver(
            $this->stateMachine,
            $this->adyenPaymentMethodChecker,
        );
    }

    public function testItDoesNothingWhenNoFailedPayments(): void
    {
        $completedPayment = $this->createMock(PaymentInterface::class);
        $completedPayment->method('getState')->willReturn(PaymentInterface::STATE_COMPLETED);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$completedPayment]));

        $this->stateMachine->expects($this->never())->method('can');
        $this->stateMachine->expects($this->never())->method('apply');

        $this->resolver->resolve($order);
    }

    public function testItDoesNothingWhenFailedPaymentsExistButNoNewAdyenPayments(): void
    {
        $failedPayment = $this->createMock(PaymentInterface::class);
        $failedPayment->method('getState')->willReturn(PaymentInterface::STATE_FAILED);

        $newNonAdyenPayment = $this->createMock(PaymentInterface::class);
        $newNonAdyenPayment->method('getState')->willReturn(PaymentInterface::STATE_NEW);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$failedPayment, $newNonAdyenPayment]));

        $this->adyenPaymentMethodChecker->method('isAdyenPayment')
            ->with($newNonAdyenPayment)
            ->willReturn(false);

        $this->stateMachine->expects($this->never())->method('can');
        $this->stateMachine->expects($this->never())->method('apply');

        $this->resolver->resolve($order);
    }

    public function testItDoesNothingWhenFailedPaymentsExistButNoNewPayments(): void
    {
        $failedPayment = $this->createMock(PaymentInterface::class);
        $failedPayment->method('getState')->willReturn(PaymentInterface::STATE_FAILED);

        $completedPayment = $this->createMock(PaymentInterface::class);
        $completedPayment->method('getState')->willReturn(PaymentInterface::STATE_COMPLETED);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$failedPayment, $completedPayment]));

        $this->stateMachine->expects($this->never())->method('can');
        $this->stateMachine->expects($this->never())->method('apply');

        $this->resolver->resolve($order);
    }

    public function testItRequestsPaymentWhenFailedPaymentsAndNewAdyenPaymentsExist(): void
    {
        $failedPayment = $this->createMock(PaymentInterface::class);
        $failedPayment->method('getState')->willReturn(PaymentInterface::STATE_FAILED);

        $newAdyenPayment = $this->createMock(PaymentInterface::class);
        $newAdyenPayment->method('getState')->willReturn(PaymentInterface::STATE_NEW);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$failedPayment, $newAdyenPayment]));

        $this->adyenPaymentMethodChecker->method('isAdyenPayment')
            ->with($newAdyenPayment)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REQUEST_PAYMENT)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REQUEST_PAYMENT);

        $this->resolver->resolve($order);
    }

    public function testItDoesNotRequestPaymentWhenTransitionNotAvailable(): void
    {
        $failedPayment = $this->createMock(PaymentInterface::class);
        $failedPayment->method('getState')->willReturn(PaymentInterface::STATE_FAILED);

        $newAdyenPayment = $this->createMock(PaymentInterface::class);
        $newAdyenPayment->method('getState')->willReturn(PaymentInterface::STATE_NEW);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([$failedPayment, $newAdyenPayment]));

        $this->adyenPaymentMethodChecker->method('isAdyenPayment')
            ->with($newAdyenPayment)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REQUEST_PAYMENT)
            ->willReturn(false);

        $this->stateMachine->expects($this->never())->method('apply');

        $this->resolver->resolve($order);
    }

    public function testItHandlesMultipleFailedAndNewAdyenPayments(): void
    {
        $failedPayment1 = $this->createMock(PaymentInterface::class);
        $failedPayment1->method('getState')->willReturn(PaymentInterface::STATE_FAILED);

        $failedPayment2 = $this->createMock(PaymentInterface::class);
        $failedPayment2->method('getState')->willReturn(PaymentInterface::STATE_FAILED);

        $newAdyenPayment1 = $this->createMock(PaymentInterface::class);
        $newAdyenPayment1->method('getState')->willReturn(PaymentInterface::STATE_NEW);

        $newAdyenPayment2 = $this->createMock(PaymentInterface::class);
        $newAdyenPayment2->method('getState')->willReturn(PaymentInterface::STATE_NEW);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getPayments')->willReturn(new ArrayCollection([
            $failedPayment1,
            $failedPayment2,
            $newAdyenPayment1,
            $newAdyenPayment2,
        ]));

        $this->adyenPaymentMethodChecker->method('isAdyenPayment')
            ->willReturnCallback(function (PaymentInterface $payment) use ($newAdyenPayment1, $newAdyenPayment2) {
                return $payment === $newAdyenPayment1 || $payment === $newAdyenPayment2;
            });

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REQUEST_PAYMENT)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REQUEST_PAYMENT);

        $this->resolver->resolve($order);
    }
}
