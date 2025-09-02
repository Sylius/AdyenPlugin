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

            $this->adyenPaymentMethodChecker
                ->expects($this->never())
                ->method('isCaptureMode');
        } else {
            $this->adyenPaymentMethodChecker
                ->expects($this->never())
                ->method('isAdyenPayment');
            $this->adyenPaymentMethodChecker
                ->expects($this->never())
                ->method('isCaptureMode');
        }

        if ($hasPayment && !$isAdyenPayment) {
            // Only delegate when we have a payment that is not Adyen
            $this->decoratedChecker
                ->expects($this->once())
                ->method('__invoke')
                ->with($orderNumber)
                ->willReturn(true);
        } else {
            // No delegation for no payment or Adyen payment
            $this->decoratedChecker
                ->expects($this->never())
                ->method('__invoke');
        }

        $result = ($this->checker)($orderNumber);

        if ($hasPayment && !$isAdyenPayment) {
            self::assertTrue($result);
        } else {
            self::assertFalse($result);
        }
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

        // Only checks automatic capture mode since it returns true
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

        // First check for automatic capture mode (will return false), then manual (will return true)
        $captureModeCallCount = 0;
        $this->adyenPaymentMethodChecker
            ->expects($this->exactly(2))
            ->method('isCaptureMode')
            ->willReturnCallback(function ($p, $mode) use ($payment, &$captureModeCallCount) {
                self::assertSame($payment, $p);
                ++$captureModeCallCount;
                if ($captureModeCallCount === 1) {
                    self::assertSame(PaymentCaptureMode::AUTOMATIC, $mode);

                    return false; // Not automatic
                }
                self::assertSame(PaymentCaptureMode::MANUAL, $mode);

                return true; // It's manual capture
            });

        // Payment state should be checked for manual capture payment
        $payment
            ->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_COMPLETED);

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

    public function testDelegatesToDecoratedCheckerForAutomaticCaptureInAllowedState(): void
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

        // First check automatic capture (returns true), then check state
        $this->adyenPaymentMethodChecker
            ->expects($this->exactly(2))
            ->method('isCaptureMode')
            ->willReturnCallback(function ($p, $mode) {
                static $callCount = 0;
                ++$callCount;
                if ($callCount === 1) {
                    self::assertSame(PaymentCaptureMode::AUTOMATIC, $mode);

                    return true; // It's automatic
                }
                self::assertSame(PaymentCaptureMode::MANUAL, $mode);

                return false; // Not manual
            });

        // Use AUTHORIZED state which is not in the blocked list for automatic capture
        $payment
            ->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_AUTHORIZED);

        $this->decoratedChecker
            ->expects($this->once())
            ->method('__invoke')
            ->with($orderNumber)
            ->willReturn(true);

        $result = ($this->checker)($orderNumber);

        self::assertTrue($result);
    }

    #[DataProvider('manualCaptureNonRefundableStatesProvider')]
    public function testReturnsFalseForManualCaptureInNonRefundableStates(string $paymentState): void
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

        // First check automatic (returns false), then manual (returns true)
        $captureModeCallCount = 0;
        $this->adyenPaymentMethodChecker
            ->expects($this->exactly(2))
            ->method('isCaptureMode')
            ->willReturnCallback(function ($p, $mode) use ($payment, &$captureModeCallCount) {
                self::assertSame($payment, $p);
                ++$captureModeCallCount;
                if ($captureModeCallCount === 1) {
                    self::assertSame(PaymentCaptureMode::AUTOMATIC, $mode);

                    return false; // Not automatic
                }
                self::assertSame(PaymentCaptureMode::MANUAL, $mode);

                return true; // It's manual
            });

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

    public static function manualCaptureNonRefundableStatesProvider(): \Generator
    {
        yield 'manual capture in new state' => [PaymentInterface::STATE_NEW];
        yield 'manual capture in processing state' => [PaymentInterface::STATE_PROCESSING];
        yield 'manual capture in failed state' => [PaymentInterface::STATE_FAILED];
        yield 'manual capture in cancelled state' => [PaymentInterface::STATE_CANCELLED];
        yield 'manual capture in authorized state' => [PaymentInterface::STATE_AUTHORIZED];
    }

    #[DataProvider('manualCaptureRefundableStatesProvider')]
    public function testDelegatesToDecoratedCheckerForManualCaptureInRefundableStates(string $paymentState): void
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

        // First check automatic (returns false), then manual (returns true)
        $captureModeCallCount = 0;
        $this->adyenPaymentMethodChecker
            ->expects($this->exactly(2))
            ->method('isCaptureMode')
            ->willReturnCallback(function ($p, $mode) use ($payment, &$captureModeCallCount) {
                self::assertSame($payment, $p);
                ++$captureModeCallCount;
                if ($captureModeCallCount === 1) {
                    self::assertSame(PaymentCaptureMode::AUTOMATIC, $mode);

                    return false; // Not automatic
                }
                self::assertSame(PaymentCaptureMode::MANUAL, $mode);

                return true; // It's manual
            });

        $payment
            ->expects($this->once())
            ->method('getState')
            ->willReturn($paymentState);

        $this->decoratedChecker
            ->expects($this->once())
            ->method('__invoke')
            ->with($orderNumber)
            ->willReturn(true);

        $result = ($this->checker)($orderNumber);

        self::assertTrue($result);
    }

    public static function manualCaptureRefundableStatesProvider(): \Generator
    {
        yield 'manual capture in completed state' => [PaymentInterface::STATE_COMPLETED];
        yield 'manual capture in refunded state' => [PaymentInterface::STATE_REFUNDED];
    }
}
