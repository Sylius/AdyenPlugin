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

namespace Tests\Sylius\AdyenPlugin\Unit\StateMachine\Guard;

use Payum\Core\Model\GatewayConfigInterface;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\StateMachine\Guard\OrderPaymentGuard;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class OrderPaymentGuardTest extends TestCase
{
    private OrderPaymentGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new OrderPaymentGuard();
    }

    public function testItAllowsCancellationForAdyenPayment(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['factory_name' => AdyenClientProviderInterface::FACTORY_NAME]);
        $gatewayConfig->method('getFactoryName')->willReturn(AdyenClientProviderInterface::FACTORY_NAME);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getState')->willReturn(PaymentInterface::STATE_NEW);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        self::assertTrue($this->guard->canBeCancelled($order));
    }

    public function testItDeniesCancellationForAdyenPaymentInProcessingReversalState(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['factory_name' => AdyenClientProviderInterface::FACTORY_NAME]);
        $gatewayConfig->method('getFactoryName')->willReturn(AdyenClientProviderInterface::FACTORY_NAME);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);
        $payment->method('getState')->willReturn(PaymentGraph::STATE_PROCESSING_REVERSAL);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        self::assertFalse($this->guard->canBeCancelled($order));
    }

    public function testItDeniesCancellationForNonAdyenPayment(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['factory_name' => 'stripe']);
        $gatewayConfig->method('getFactoryName')->willReturn('stripe');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        self::assertFalse($this->guard->canBeCancelled($order));
    }

    public function testItDeniesCancellationWhenPaymentIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn(null);

        self::assertFalse($this->guard->canBeCancelled($order));
    }

    public function testItDeniesCancellationWhenPaymentMethodIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn(null);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        self::assertFalse($this->guard->canBeCancelled($order));
    }

    public function testItDeniesCancellationWhenGatewayConfigIsNull(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn(null);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        self::assertFalse($this->guard->canBeCancelled($order));
    }

    public function testItDeniesCancellationWhenFactoryNameIsNotSet(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn([]);
        $gatewayConfig->method('getFactoryName')->willReturn(null);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        self::assertFalse($this->guard->canBeCancelled($order));
    }

    public function testItPrioritizesFactoryNameFromConfigOverGatewayFactoryName(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getConfig')->willReturn(['factory_name' => AdyenClientProviderInterface::FACTORY_NAME]);
        $gatewayConfig->method('getFactoryName')->willReturn('some_other_factory');

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->method('getGatewayConfig')->willReturn($gatewayConfig);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getMethod')->willReturn($paymentMethod);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getLastPayment')->willReturn($payment);

        self::assertTrue($this->guard->canBeCancelled($order));
    }
}
