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

use Payum\Core\Model\GatewayConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Processor\Order\UpdateOrderPaymentStateProcessor;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderPaymentTransitions;

final class CancelOrderPaymentProcessorTest extends TestCase
{
    private UpdateOrderPaymentStateProcessor $processor;

    private MockObject|StateMachineInterface $stateMachine;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->processor = new UpdateOrderPaymentStateProcessor($this->stateMachine);
    }

    public function testDoesNothingWhenOrderIsNull(): void
    {
        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process(null);
    }

    public function testDoesNothingWhenOrderPaymentStateIsNotPaid(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $order
            ->expects($this->never())
            ->method('getLastPayment');

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testDoesNothingWhenPaymentIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn(null);

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testDoesNothingWhenPaymentIsNotAdyen(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => 'stripe']);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testDoesNothingWhenPaymentMethodIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn(null);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testDoesNothingWhenGatewayConfigIsNull(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testAppliesCancelTransitionWhenCanTransition(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => AdyenClientProviderInterface::FACTORY_NAME]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL)
            ->willReturn(true);

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL);

        $this->processor->process($order);
    }

    public function testDoesNotApplyCancelTransitionWhenCannotTransition(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => AdyenClientProviderInterface::FACTORY_NAME]);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $matcher = $this->exactly(2);
        $this->stateMachine
            ->expects($matcher)
            ->method('can')
            ->willReturnCallback(function ($arg1, $arg2, $arg3) use ($matcher, $order) {
                $this->assertSame($order, $arg1);
                $this->assertSame(OrderPaymentTransitions::GRAPH, $arg2);

                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertSame(OrderPaymentTransitions::TRANSITION_CANCEL, $arg3),
                    2 => $this->assertSame(OrderPaymentTransitions::TRANSITION_REFUND, $arg3),
                };

                return false;
            });

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }

    public function testChecksFactoryNameFromGatewayConfigWhenConfigArrayIsEmpty(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $gatewayConfig
            ->expects($this->once())
            ->method('getFactoryName')
            ->willReturn(AdyenClientProviderInterface::FACTORY_NAME);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL)
            ->willReturn(true);

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL);

        $this->processor->process($order);
    }

    public function testDoesNothingWhenFactoryNameDoesNotMatchAdyen(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $gatewayConfig
            ->expects($this->once())
            ->method('getFactoryName')
            ->willReturn('stripe');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getPaymentState')
            ->willReturn(OrderPaymentStates::STATE_PAID);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        $this->processor->process($order);
    }
}
