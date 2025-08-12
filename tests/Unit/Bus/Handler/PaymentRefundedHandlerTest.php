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

namespace Tests\Sylius\AdyenPlugin\Unit\Bus\Handler;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\PaymentRefundedCommand;
use Sylius\AdyenPlugin\Bus\Handler\PaymentRefundedHandler;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\Amount;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Factory\RefundPaymentFactoryInterface;
use Sylius\RefundPlugin\StateResolver\RefundPaymentTransitions;

class PaymentRefundedHandlerTest extends TestCase
{
    private StateMachineInterface|MockObject $stateMachine;

    private RefundPaymentFactoryInterface|MockObject $refundPaymentFactory;

    private EntityManagerInterface|MockObject $entityManager;

    private PaymentRefundedHandler $handler;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->refundPaymentFactory = $this->createMock(RefundPaymentFactoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new PaymentRefundedHandler(
            $this->stateMachine,
            $this->refundPaymentFactory,
            $this->entityManager,
        );
    }

    public function testInvokeCreatesRefundPaymentAndUpdatesStates(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);

        $amount = new Amount();
        $amount->value = 1000;
        $amount->currency = 'EUR';

        $notificationData = new NotificationItemData();
        $notificationData->amount = $amount;

        $command = new PaymentRefundedCommand($payment, $notificationData);

        $payment->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $this->refundPaymentFactory->expects($this->once())
            ->method('createWithData')
            ->with(
                $order,
                1000,
                'EUR',
                RefundPaymentInterface::STATE_COMPLETED,
                $paymentMethod,
            )
            ->willReturn($refundPayment);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($refundPayment);
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->stateMachine->expects($this->exactly(2))
            ->method('can')
            ->willReturnCallback(function ($subject, $graph, $transition) use ($refundPayment, $payment) {
                if ($subject === $refundPayment && $graph === RefundPaymentTransitions::GRAPH && $transition === RefundPaymentTransitions::TRANSITION_COMPLETE) {
                    return true;
                }
                if ($subject === $payment && $graph === PaymentTransitions::GRAPH && $transition === PaymentTransitions::TRANSITION_REFUND) {
                    return true;
                }

                return false;
            });

        $this->stateMachine->expects($this->exactly(2))
            ->method('apply')
            ->willReturnCallback(function ($subject, $graph, $transition) use ($refundPayment, $payment) {
                if ($subject === $refundPayment && $graph === RefundPaymentTransitions::GRAPH && $transition === RefundPaymentTransitions::TRANSITION_COMPLETE) {
                    return true;
                }
                if ($subject === $payment && $graph === PaymentTransitions::GRAPH && $transition === PaymentTransitions::TRANSITION_REFUND) {
                    return true;
                }

                return false;
            });

        $this->handler->__invoke($command);
    }

    public function testInvokeUsesPaymentAmountWhenNotificationAmountIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);

        $notificationData = new NotificationItemData();
        $notificationData->amount = null;

        $command = new PaymentRefundedCommand($payment, $notificationData);

        $payment->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);
        $payment->expects($this->once())
            ->method('getAmount')
            ->willReturn(1500);
        $payment->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');

        $this->refundPaymentFactory->expects($this->once())
            ->method('createWithData')
            ->with(
                $order,
                1500,
                'USD',
                RefundPaymentInterface::STATE_COMPLETED,
                $paymentMethod,
            )
            ->willReturn($refundPayment);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->stateMachine->expects($this->exactly(2))->method('can')->willReturn(true);
        $this->stateMachine->expects($this->exactly(2))->method('apply');

        $this->handler->__invoke($command);
    }

    public function testInvokeSkipsStateTransitionsWhenNotPossible(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);

        $notificationData = new NotificationItemData();
        $notificationData->amount = null;

        $command = new PaymentRefundedCommand($payment, $notificationData);

        $payment->expects($this->once())->method('getOrder')->willReturn($order);
        $payment->expects($this->once())->method('getMethod')->willReturn($paymentMethod);
        $payment->expects($this->once())->method('getAmount')->willReturn(1000);
        $payment->expects($this->once())->method('getCurrencyCode')->willReturn('EUR');

        $this->refundPaymentFactory->expects($this->once())
            ->method('createWithData')
            ->willReturn($refundPayment);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->stateMachine->expects($this->exactly(2))
            ->method('can')
            ->willReturn(false);
        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->handler->__invoke($command);
    }
}
