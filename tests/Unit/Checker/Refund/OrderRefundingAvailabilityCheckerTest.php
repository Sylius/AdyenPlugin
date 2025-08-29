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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker\Refund;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Checker\Refund\OrderRefundingAvailabilityChecker;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\RefundPlugin\Checker\OrderRefundingAvailabilityCheckerInterface;
use Webmozart\Assert\InvalidArgumentException;

final class OrderRefundingAvailabilityCheckerTest extends TestCase
{
    private OrderRefundingAvailabilityChecker $checker;

    private MockObject|OrderRefundingAvailabilityCheckerInterface $decoratedChecker;

    private MockObject|OrderRepositoryInterface $orderRepository;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    protected function setUp(): void
    {
        $this->decoratedChecker = $this->createMock(OrderRefundingAvailabilityCheckerInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);

        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);

        $this->checker = new OrderRefundingAvailabilityChecker(
            $this->decoratedChecker,
            $this->orderRepository,
            $this->adyenPaymentMethodChecker,
        );
    }

    public function testThrowsExceptionWhenOrderIsNull(): void
    {
        $orderNumber = 'ORDER-123';

        $this->orderRepository
            ->expects($this->once())
            ->method('findOneByNumber')
            ->with($orderNumber)
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);

        ($this->checker)($orderNumber);
    }

    #[DataProvider('delegationScenariosProvider')]
    public function testDelegatesToDecoratedChecker(
        bool $hasPayment,
        bool $isAdyenPayment,
    ): void {
        $orderNumber = 'ORDER-123';
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $this->orderRepository
            ->expects($this->once())
            ->method('findOneByNumber')
            ->with($orderNumber)
            ->willReturn($order);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($hasPayment ? $payment : null);

        if ($hasPayment) {
            $this->adyenPaymentMethodChecker
                ->expects($this->once())
                ->method('isAdyenPayment')
                ->with($payment)
                ->willReturn($isAdyenPayment);
        }

        $this->decoratedChecker
            ->expects($this->once())
            ->method('__invoke')
            ->with($orderNumber)
            ->willReturn(true);

        $result = ($this->checker)($orderNumber);

        self::assertTrue($result);
    }

    public static function delegationScenariosProvider(): \Generator
    {
        yield 'no payment' => [
            'hasPayment' => false,
            'isAdyenPayment' => false,
        ];

        yield 'non-Adyen payment' => [
            'hasPayment' => true,
            'isAdyenPayment' => false,
        ];
    }

    #[DataProvider('adyenPaymentStatesProvider')]
    public function testReturnsFalseForAdyenPaymentsWithAutomaticCaptureInSpecificStates(
        string $paymentState,
    ): void {
        $orderNumber = 'ORDER-123';
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $this->orderRepository
            ->expects($this->once())
            ->method('findOneByNumber')
            ->with($orderNumber)
            ->willReturn($order);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true);

        $payment
            ->expects($this->once())
            ->method('getState')
            ->willReturn($paymentState);

        $this->decoratedChecker
            ->expects($this->never())
            ->method('__invoke');

        $result = ($this->checker)($orderNumber);

        self::assertFalse($result);
    }

    public function testDelegatesToDecoratedCheckerForAdyenPaymentsWithManualCapture(): void
    {
        $orderNumber = 'ORDER-123';
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $this->orderRepository
            ->expects($this->once())
            ->method('findOneByNumber')
            ->with($orderNumber)
            ->willReturn($order);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false); // Manual capture mode

        $this->decoratedChecker
            ->expects($this->once())
            ->method('__invoke')
            ->with($orderNumber)
            ->willReturn(true);

        $result = ($this->checker)($orderNumber);

        self::assertTrue($result);
    }

    public static function adyenPaymentStatesProvider(): \Generator
    {
        yield 'Adyen payment in processing reversal state' => [
            'paymentState' => PaymentGraph::STATE_PROCESSING_REVERSAL,
        ];

        yield 'Adyen payment in completed state' => [
            'paymentState' => PaymentInterface::STATE_COMPLETED,
        ];

        yield 'Adyen payment in refunded state' => [
            'paymentState' => PaymentInterface::STATE_REFUNDED,
        ];
    }
}
