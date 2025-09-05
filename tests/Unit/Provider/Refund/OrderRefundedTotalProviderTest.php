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
use Sylius\AdyenPlugin\Provider\Refund\OrderRefundedTotalProvider;
use Sylius\AdyenPlugin\Repository\RefundPaymentRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Provider\OrderRefundedTotalProviderInterface;

final class OrderRefundedTotalProviderTest extends TestCase
{
    private MockObject|OrderRefundedTotalProviderInterface $decoratedProvider;

    private MockObject|RefundPaymentRepositoryInterface $refundPaymentRepository;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private OrderRefundedTotalProvider $provider;

    protected function setUp(): void
    {
        $this->decoratedProvider = $this->createMock(OrderRefundedTotalProviderInterface::class);
        $this->refundPaymentRepository = $this->createMock(RefundPaymentRepositoryInterface::class);
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);

        $this->provider = new OrderRefundedTotalProvider(
            $this->decoratedProvider,
            $this->refundPaymentRepository,
            $this->adyenPaymentMethodChecker,
        );
    }

    public function test_it_delegates_to_decorated_provider_when_order_has_no_last_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $expectedTotal = 2000;

        $order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn(null);

        $this->refundPaymentRepository->expects($this->never())
            ->method('findBy')
            ->with(['order' => $order])
            ->willReturn([]);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($expectedTotal);

        $result = ($this->provider)($order);

        self::assertSame($expectedTotal, $result);
    }

    public function test_it_delegates_to_decorated_provider_when_last_payment_is_not_adyen(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $expectedTotal = 2000;

        $order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('__invoke')
            ->with($order)
            ->willReturn($expectedTotal);

        $result = ($this->provider)($order);

        self::assertSame($expectedTotal, $result);
    }

    public function test_it_calculates_refunded_total_from_completed_refund_payments_when_payment_is_adyen(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $completedRefundPayment1 = $this->createMock(RefundPaymentInterface::class);
        $completedRefundPayment2 = $this->createMock(RefundPaymentInterface::class);
        $pendingRefundPayment = $this->createMock(RefundPaymentInterface::class);

        $refundPayments = [$completedRefundPayment1, $completedRefundPayment2, $pendingRefundPayment];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->refundPaymentRepository->expects($this->once())
            ->method('findBy')
            ->with(['order' => $order])
            ->willReturn($refundPayments);

        $completedRefundPayment1->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_COMPLETED);
        $completedRefundPayment1->expects($this->once())
            ->method('getAmount')
            ->willReturn(500);

        $completedRefundPayment2->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_COMPLETED);
        $completedRefundPayment2->expects($this->once())
            ->method('getAmount')
            ->willReturn(300);

        $pendingRefundPayment->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_NEW);

        $this->decoratedProvider
            ->expects($this->never())
            ->method('__invoke');

        $result = ($this->provider)($order);

        self::assertSame(800, $result);
    }

    public function test_it_returns_zero_when_no_completed_refund_payments_exist_for_adyen_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $pendingRefundPayment1 = $this->createMock(RefundPaymentInterface::class);
        $pendingRefundPayment2 = $this->createMock(RefundPaymentInterface::class);

        $refundPayments = [$pendingRefundPayment1, $pendingRefundPayment2];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->refundPaymentRepository->expects($this->once())
            ->method('findBy')
            ->with(['order' => $order])
            ->willReturn($refundPayments);

        $pendingRefundPayment1->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_NEW);

        $pendingRefundPayment2->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_NEW);

        $this->decoratedProvider
            ->expects($this->never())
            ->method('__invoke');

        $result = ($this->provider)($order);

        self::assertSame(0, $result);
    }

    public function test_it_returns_zero_when_no_refund_payments_exist_for_adyen_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->refundPaymentRepository->expects($this->once())
            ->method('findBy')
            ->with(['order' => $order])
            ->willReturn([]);

        $this->decoratedProvider
            ->expects($this->never())
            ->method('__invoke');

        $result = ($this->provider)($order);

        self::assertSame(0, $result);
    }

    public function test_it_handles_mixed_refund_payment_states_for_adyen_payment(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $completedRefundPayment1 = $this->createMock(RefundPaymentInterface::class);
        $completedRefundPayment2 = $this->createMock(RefundPaymentInterface::class);
        $newRefundPayment1 = $this->createMock(RefundPaymentInterface::class);
        $newRefundPayment2 = $this->createMock(RefundPaymentInterface::class);

        $refundPayments = [
            $completedRefundPayment1,
            $newRefundPayment1,
            $completedRefundPayment2,
            $newRefundPayment2,
        ];

        $order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->refundPaymentRepository->expects($this->once())
            ->method('findBy')
            ->with(['order' => $order])
            ->willReturn($refundPayments);

        $completedRefundPayment1->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_COMPLETED);
        $completedRefundPayment1->expects($this->once())
            ->method('getAmount')
            ->willReturn(1000);

        $newRefundPayment1->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_NEW);

        $completedRefundPayment2->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_COMPLETED);
        $completedRefundPayment2->expects($this->once())
            ->method('getAmount')
            ->willReturn(750);

        $newRefundPayment2->expects($this->once())
            ->method('getState')
            ->willReturn(RefundPaymentInterface::STATE_NEW);

        $this->decoratedProvider
            ->expects($this->never())
            ->method('__invoke');

        $result = ($this->provider)($order);

        self::assertSame(1750, $result);
    }
}
