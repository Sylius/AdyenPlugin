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

namespace Tests\Sylius\AdyenPlugin\Unit\Processor\Payment;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\RequestCapture;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Exception\PaymentActionException;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Processor\Payment\ManualCaptureProcessor;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ManualCaptureProcessorTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private MessageBusInterface|MockObject $messageBus;

    private ManualCaptureProcessor $processor;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->processor = new ManualCaptureProcessor(
            $this->adyenPaymentMethodChecker,
            $this->messageBus,
        );
    }

    public function testProcessThrowsExceptionWhenPaymentIsNotAdyenPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects(self::once())
            ->method('getId')
            ->willReturn(123);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        $this->adyenPaymentMethodChecker
            ->expects(self::never())
            ->method('isCaptureMode');

        $this->messageBus
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(PaymentActionException::class);
        $this->expectExceptionMessage('Cannot capture non Adyen payment (ID: 123)');

        $this->processor->process($payment);
    }

    public function testProcessThrowsExceptionWhenPaymentHasAutomaticCaptureMode(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects(self::once())
            ->method('getId')
            ->willReturn(456);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true);

        $payment
            ->expects(self::never())
            ->method('getState');

        $this->messageBus
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(PaymentActionException::class);
        $this->expectExceptionMessage('Cannot manually capture payment (ID: 456) with automatic capture mode');

        $this->processor->process($payment);
    }

    public function testProcessThrowsExceptionWhenPaymentIsNotInAuthorizedState(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment
            ->expects(self::once())
            ->method('getId')
            ->willReturn(789);

        $payment
            ->expects(self::exactly(2))
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false);

        $payment
            ->expects(self::never())
            ->method('getOrder');

        $this->messageBus
            ->expects(self::never())
            ->method('dispatch');

        $this->expectException(PaymentActionException::class);
        $this->expectExceptionMessage('Cannot capture payment (ID: 789) that is not in authorized state, current state: new');

        $this->processor->process($payment);
    }

    public function testProcessDispatchesRequestCaptureCommandWhenAllConditionsAreMet(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $payment
            ->expects(self::never())
            ->method('getId');

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_AUTHORIZED);

        $payment
            ->expects(self::once())
            ->method('getOrder')
            ->willReturn($order);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false);

        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function ($command) use ($order) {
                return $command instanceof RequestCapture && $command->getOrder() === $order;
            }))
            ->willReturn(new Envelope(new RequestCapture($order)));

        $this->processor->process($payment);
    }

    public function testProcessWithDifferentInvalidStates(): void
    {
        $invalidStates = [
            PaymentInterface::STATE_NEW,
            PaymentInterface::STATE_PROCESSING,
            PaymentInterface::STATE_COMPLETED,
            PaymentInterface::STATE_FAILED,
            PaymentInterface::STATE_CANCELLED,
            PaymentInterface::STATE_REFUNDED,
        ];

        foreach ($invalidStates as $state) {
            $payment = $this->createMock(PaymentInterface::class);
            $payment
                ->expects(self::once())
                ->method('getId')
                ->willReturn(100);

            $payment
                ->expects(self::exactly(2))
                ->method('getState')
                ->willReturn($state);

            $this->adyenPaymentMethodChecker
                ->expects(self::once())
                ->method('isAdyenPayment')
                ->with($payment)
                ->willReturn(true);

            $this->adyenPaymentMethodChecker
                ->expects(self::once())
                ->method('isCaptureMode')
                ->with($payment, PaymentCaptureMode::AUTOMATIC)
                ->willReturn(false);

            $this->messageBus
                ->expects(self::never())
                ->method('dispatch');

            $this->expectException(PaymentActionException::class);
            $this->expectExceptionMessage("Cannot capture payment (ID: 100) that is not in authorized state, current state: $state");

            $this->processor->process($payment);

            $this->setUp();
        }
    }
}
