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
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Provider\Refund\SupportedRefundPaymentMethodsProvider;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\RefundPlugin\Provider\RefundPaymentMethodsProviderInterface;

final class SupportedRefundPaymentMethodsProviderTest extends TestCase
{
    private MockObject|MockRefundPaymentMethodsProvider $decoratedProvider;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private SupportedRefundPaymentMethodsProvider $provider;

    protected function setUp(): void
    {
        $this->decoratedProvider = $this->createMock(MockRefundPaymentMethodsProvider::class);
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->provider = new SupportedRefundPaymentMethodsProvider($this->decoratedProvider, $this->adyenPaymentMethodChecker);
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

    public function test_it_returns_empty_array_when_order_has_no_completed_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn(null);

        $result = $this->provider->findForOrder($order);

        self::assertSame([], $result);
    }

    public function test_it_returns_enabled_adyen_payment_method_and_non_adyen_methods_when_last_payment_is_adyen(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $adyenMethod = $this->createMock(PaymentMethodInterface::class);
        $nonAdyenMethod = $this->createMock(PaymentMethodInterface::class);
        $decoratedProviderMethods = [$adyenMethod, $nonAdyenMethod];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $payment->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->exactly(2))
            ->method('isAdyenPaymentMethod')
            ->willReturnCallback(fn ($method) => $method === $adyenMethod);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForOrder')
            ->with($order)
            ->willReturn($decoratedProviderMethods);

        $result = $this->provider->findForOrder($order);

        self::assertCount(2, $result);
        self::assertSame($paymentMethod, $result[0]);
        self::assertContains($nonAdyenMethod, $result);
        self::assertNotContains($adyenMethod, $result);
    }

    public function test_it_returns_only_non_adyen_methods_when_adyen_payment_method_is_disabled(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $adyenMethod = $this->createMock(PaymentMethodInterface::class);
        $nonAdyenMethod = $this->createMock(PaymentMethodInterface::class);
        $decoratedProviderMethods = [$adyenMethod, $nonAdyenMethod];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $payment->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->exactly(2))
            ->method('isAdyenPaymentMethod')
            ->willReturnCallback(fn ($method) => $method === $adyenMethod);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForOrder')
            ->with($order)
            ->willReturn($decoratedProviderMethods);

        $result = $this->provider->findForOrder($order);

        self::assertCount(1, $result);
        self::assertContains($nonAdyenMethod, $result);
        self::assertNotContains($adyenMethod, $result);
    }

    public function test_it_filters_out_adyen_methods_when_last_payment_is_not_adyen(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $adyenMethod = $this->createMock(PaymentMethodInterface::class);
        $nonAdyenMethod = $this->createMock(PaymentMethodInterface::class);
        $allMethods = [$adyenMethod, $nonAdyenMethod];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        $this->adyenPaymentMethodChecker->expects($this->exactly(2))
            ->method('isAdyenPaymentMethod')
            ->willReturnCallback(fn ($method) => $method === $adyenMethod);

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

    public function test_it_returns_only_the_payment_method_used_and_non_adyen_methods_when_multiple_adyen_methods_exist(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $adyenMethod1 = $this->createMock(PaymentMethodInterface::class);
        $adyenMethod2 = $this->createMock(PaymentMethodInterface::class);
        $nonAdyenMethod = $this->createMock(PaymentMethodInterface::class);
        $decoratedProviderMethods = [$adyenMethod1, $adyenMethod2, $nonAdyenMethod];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->with(PaymentInterface::STATE_COMPLETED)
            ->willReturn($payment);

        $payment->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->exactly(3))
            ->method('isAdyenPaymentMethod')
            ->willReturnCallback(fn ($method) => $method === $adyenMethod1 || $method === $adyenMethod2);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('findForOrder')
            ->with($order)
            ->willReturn($decoratedProviderMethods);

        $result = $this->provider->findForOrder($order);

        self::assertCount(2, $result);
        self::assertSame($paymentMethod, $result[0]);
        self::assertContains($nonAdyenMethod, $result);
        self::assertNotContains($adyenMethod1, $result);
        self::assertNotContains($adyenMethod2, $result);
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
