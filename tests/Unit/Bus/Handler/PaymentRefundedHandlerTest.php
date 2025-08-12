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
use Sylius\AdyenPlugin\Bus\Command\PaymentRefunded;
use Sylius\AdyenPlugin\Bus\Handler\PaymentRefundedHandler;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Factory\AdyenReferenceFactoryInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\Amount;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Factory\RefundPaymentFactoryInterface;
use Sylius\RefundPlugin\StateResolver\RefundPaymentTransitions;

class PaymentRefundedHandlerTest extends TestCase
{
    private MockObject|StateMachineInterface $stateMachine;

    private MockObject|RefundPaymentFactoryInterface $refundPaymentFactory;

    private AdyenReferenceFactoryInterface|MockObject $referenceFactory;

    private AdyenReferenceRepositoryInterface|MockObject $referenceRepository;

    private EntityManagerInterface|MockObject $entityManager;

    private PaymentRefundedHandler $handler;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->refundPaymentFactory = $this->createMock(RefundPaymentFactoryInterface::class);
        $this->referenceFactory = $this->createMock(AdyenReferenceFactoryInterface::class);
        $this->referenceRepository = $this->createMock(AdyenReferenceRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new PaymentRefundedHandler(
            $this->stateMachine,
            $this->refundPaymentFactory,
            $this->referenceFactory,
            $this->referenceRepository,
            $this->entityManager,
        );
    }

    public function testInvokeCreatesNewRefundPaymentWhenReferenceNotFound(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $adyenReference = $this->createMock(AdyenReferenceInterface::class);

        $amount = new Amount();
        $amount->value = 1000;
        $amount->currency = 'EUR';

        $notificationData = new NotificationItemData();
        $notificationData->amount = $amount;
        $notificationData->pspReference = 'TEST_PSP_REF_123';

        $command = new PaymentRefunded($payment, $notificationData);

        $payment->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getCode')
            ->willReturn('adyen_payment');

        $this->referenceRepository->expects($this->once())
            ->method('getOneForRefundByCodeAndReference')
            ->with('adyen_payment', 'TEST_PSP_REF_123')
            ->willThrowException(new \Exception('Reference not found'));

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

        $this->referenceFactory->expects($this->once())
            ->method('createForRefund')
            ->with(
                'TEST_PSP_REF_123',
                $payment,
                $refundPayment,
            )
            ->willReturn($adyenReference);

        $this->referenceRepository->expects($this->once())
            ->method('add')
            ->with($adyenReference);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($refundPayment);
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, RefundPaymentTransitions::TRANSITION_COMPLETE)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, RefundPaymentTransitions::TRANSITION_COMPLETE);

        $this->handler->__invoke($command);
    }

    public function testInvokeUsesExistingRefundPaymentWhenReferenceFound(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $adyenReference = $this->createMock(AdyenReferenceInterface::class);

        $amount = new Amount();
        $amount->value = 1000;
        $amount->currency = 'EUR';

        $notificationData = new NotificationItemData();
        $notificationData->amount = $amount;
        $notificationData->pspReference = 'TEST_PSP_REF_123';

        $command = new PaymentRefunded($payment, $notificationData);

        $payment->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getCode')
            ->willReturn('adyen_payment');

        $adyenReference->expects($this->once())
            ->method('getRefundPayment')
            ->willReturn($refundPayment);

        $this->referenceRepository->expects($this->once())
            ->method('getOneForRefundByCodeAndReference')
            ->with('adyen_payment', 'TEST_PSP_REF_123')
            ->willReturn($adyenReference);

        $this->refundPaymentFactory->expects($this->never())
            ->method('createWithData');

        $this->referenceFactory->expects($this->never())
            ->method('createForRefund');

        $this->referenceRepository->expects($this->never())
            ->method('add');

        $this->entityManager->expects($this->never())
            ->method('persist');
        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, RefundPaymentTransitions::TRANSITION_COMPLETE)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, RefundPaymentTransitions::TRANSITION_COMPLETE);

        $this->handler->__invoke($command);
    }

    public function testInvokeUsesPaymentAmountWhenNotificationAmountIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $adyenReference = $this->createMock(AdyenReferenceInterface::class);

        $notificationData = new NotificationItemData();
        $notificationData->amount = null;
        $notificationData->pspReference = 'TEST_PSP_REF_456';

        $command = new PaymentRefunded($payment, $notificationData);

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

        $paymentMethod->expects($this->once())
            ->method('getCode')
            ->willReturn('adyen_payment');

        $this->referenceRepository->expects($this->once())
            ->method('getOneForRefundByCodeAndReference')
            ->with('adyen_payment', 'TEST_PSP_REF_456')
            ->willThrowException(new \Exception('Reference not found'));

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

        $this->referenceFactory->expects($this->once())
            ->method('createForRefund')
            ->with(
                'TEST_PSP_REF_456',
                $payment,
                $refundPayment,
            )
            ->willReturn($adyenReference);

        $this->referenceRepository->expects($this->once())
            ->method('add')
            ->with($adyenReference);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        $this->stateMachine->expects($this->once())->method('can')->willReturn(true);
        $this->stateMachine->expects($this->once())->method('apply');

        $this->handler->__invoke($command);
    }

    public function testInvokeSkipsStateTransitionsWhenNotPossible(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $adyenReference = $this->createMock(AdyenReferenceInterface::class);

        $notificationData = new NotificationItemData();
        $notificationData->amount = null;
        $notificationData->pspReference = 'TEST_PSP_REF_789';

        $command = new PaymentRefunded($payment, $notificationData);

        $payment->expects($this->once())->method('getOrder')->willReturn($order);
        $payment->expects($this->once())->method('getMethod')->willReturn($paymentMethod);
        $payment->expects($this->once())->method('getAmount')->willReturn(1000);
        $payment->expects($this->once())->method('getCurrencyCode')->willReturn('EUR');

        $paymentMethod->expects($this->once())
            ->method('getCode')
            ->willReturn('adyen_payment');

        $this->referenceRepository->expects($this->once())
            ->method('getOneForRefundByCodeAndReference')
            ->with('adyen_payment', 'TEST_PSP_REF_789')
            ->willThrowException(new \Exception('Reference not found'));

        $this->refundPaymentFactory->expects($this->once())
            ->method('createWithData')
            ->willReturn($refundPayment);

        $this->referenceFactory->expects($this->once())
            ->method('createForRefund')
            ->with(
                'TEST_PSP_REF_789',
                $payment,
                $refundPayment,
            )
            ->willReturn($adyenReference);

        $this->referenceRepository->expects($this->once())
            ->method('add')
            ->with($adyenReference);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->willReturn(false);
        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->handler->__invoke($command);
    }
}
