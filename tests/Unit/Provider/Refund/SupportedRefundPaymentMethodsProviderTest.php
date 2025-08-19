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

namespace Tests\Sylius\AdyenPlugin\Unit\Provider\Refund;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Provider\Refund\SupportedRefundPaymentMethodsProvider;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\RefundPlugin\Provider\RefundPaymentMethodsProviderInterface;

final class SupportedRefundPaymentMethodsProviderTest extends TestCase
{
    private MockObject|MockRefundPaymentMethodsProvider $decoratedProvider;

    private SupportedRefundPaymentMethodsProvider $provider;

    protected function setUp(): void
    {
        $this->decoratedProvider = $this->createMock(MockRefundPaymentMethodsProvider::class);
        $this->provider = new SupportedRefundPaymentMethodsProvider($this->decoratedProvider);
    }

    public function test_it_delegates_find_for_channel_to_decorated_provider(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $expectedMethods = [$this->createMock(PaymentMethodInterface::class)];

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForChannel')
            ->with($channel)
            ->willReturn($expectedMethods);

        $result = $this->provider->findForChannel($channel);

        self::assertSame($expectedMethods, $result);
    }

    public function test_it_returns_all_methods_when_order_has_no_completed_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $expectedMethods = [$this->createMock(PaymentMethodInterface::class)];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn(null);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForOrder')
            ->with($order)
            ->willReturn($expectedMethods);

        $result = $this->provider->findForOrder($order);

        self::assertSame($expectedMethods, $result);
    }

    public function test_it_returns_all_methods_when_last_payment_is_adyen_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $expectedMethods = [$paymentMethod];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => 'adyen']);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForOrder')
            ->with($order)
            ->willReturn($expectedMethods);

        $result = $this->provider->findForOrder($order);

        self::assertSame($expectedMethods, $result);
    }

    public function test_it_filters_out_adyen_methods_when_last_payment_is_not_adyen(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $adyenMethod = $this->createMock(PaymentMethodInterface::class);
        $adyenGatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $nonAdyenMethod = $this->createMock(PaymentMethodInterface::class);
        $nonAdyenGatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $allMethods = [$adyenMethod, $nonAdyenMethod];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => 'paypal']);

        // Mock Adyen method
        $adyenMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($adyenGatewayConfig);

        $adyenGatewayConfig->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => 'adyen']);

        // Mock non-Adyen method
        $nonAdyenMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($nonAdyenGatewayConfig);

        $nonAdyenGatewayConfig->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => 'stripe']);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForOrder')
            ->with($order)
            ->willReturn($allMethods);

        $result = $this->provider->findForOrder($order);

        self::assertCount(1, $result);
        self::assertContains($nonAdyenMethod, $result);
        self::assertNotContains($adyenMethod, $result);
    }

    public function test_it_handles_payment_method_with_null_gateway_config(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $methodWithNullConfig = $this->createMock(PaymentMethodInterface::class);
        $validMethod = $this->createMock(PaymentMethodInterface::class);
        $validGatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $allMethods = [$methodWithNullConfig, $validMethod];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => 'paypal']);

        // Method with null gateway config
        $methodWithNullConfig->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn(null);

        // Valid method
        $validMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($validGatewayConfig);

        $validGatewayConfig->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => 'stripe']);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForOrder')
            ->with($order)
            ->willReturn($allMethods);

        $result = $this->provider->findForOrder($order);

        self::assertCount(2, $result);
        self::assertContains($methodWithNullConfig, $result);
        self::assertContains($validMethod, $result);
    }

    public function test_it_handles_gateway_config_with_factory_name_fallback(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $methodWithFactoryNameFallback = $this->createMock(PaymentMethodInterface::class);
        $fallbackGatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $allMethods = [$methodWithFactoryNameFallback];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => 'paypal']);

        // Method that uses getFactoryName() fallback
        $methodWithFactoryNameFallback->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($fallbackGatewayConfig);

        $fallbackGatewayConfig->expects($this->once())
            ->method('getConfig')
            ->willReturn([]); // No factory_name in config

        $fallbackGatewayConfig->expects($this->once())
            ->method('getFactoryName')
            ->willReturn('adyen');

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForOrder')
            ->with($order)
            ->willReturn($allMethods);

        $result = $this->provider->findForOrder($order);

        self::assertCount(0, $result);
    }
}

// Necessary since the interface does not describe all methods
class MockRefundPaymentMethodsProvider implements RefundPaymentMethodsProviderInterface
{
    public function findForChannel(ChannelInterface $channel): array
    {
        return [];
    }

    public function findForOrder(OrderInterface $order): array
    {
        return [];
    }
}
