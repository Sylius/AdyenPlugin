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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\PaymentRefunded;
use Sylius\AdyenPlugin\Bus\Handler\PaymentRefundedHandler;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Factory\AdyenReferenceFactoryInterface;
use Sylius\AdyenPlugin\RefundPaymentTransitions as AdyenRefundPaymentTransitions;
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
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, AdyenRefundPaymentTransitions::TRANSITION_CONFIRM)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, AdyenRefundPaymentTransitions::TRANSITION_CONFIRM);

        $this->handler->__invoke($command);
    }

    #[DataProvider('provideRefundCreationScenarios')]
    public function testInvokeCreatesNewRefundPaymentWhenReferenceNotFound(
        ?Amount $amount,
        int $expectedAmount,
        string $expectedCurrency,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);
        $adyenReference = $this->createMock(AdyenReferenceInterface::class);

        $notificationData = new NotificationItemData();
        $notificationData->amount = $amount;
        $notificationData->pspReference = 'TEST_PSP_REF_123';
        $notificationData->originalReference = 'ORIGINAL_PSP_REF';

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

        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn(['pspReference' => 'ORIGINAL_PSP_REF']);

        if ($amount === null || $amount->value === null) {
            $payment->expects($this->once())
                ->method('getAmount')
                ->willReturn($expectedAmount);
        }
        if ($amount === null || $amount->currency === null) {
            $payment->expects($this->once())
                ->method('getCurrencyCode')
                ->willReturn($expectedCurrency);
        }

        $this->refundPaymentFactory->expects($this->once())
            ->method('createWithData')
            ->with(
                $order,
                $expectedAmount,
                $expectedCurrency,
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
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, AdyenRefundPaymentTransitions::TRANSITION_CONFIRM)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, AdyenRefundPaymentTransitions::TRANSITION_CONFIRM);

        $this->handler->__invoke($command);
    }

    public static function provideRefundCreationScenarios(): iterable
    {
        $amount = new Amount();
        $amount->value = 1000;
        $amount->currency = 'EUR';

        yield 'creates refund with notification amount' => [
            'amount' => $amount,
            'expectedAmount' => 1000,
            'expectedCurrency' => 'EUR',
        ];

        $amountWithoutValue = new Amount();
        $amountWithoutValue->currency = 'USD';

        yield 'uses payment amount when notification amount value is null' => [
            'amount' => $amountWithoutValue,
            'expectedAmount' => 1500,
            'expectedCurrency' => 'USD',
        ];

        $amountWithoutCurrency = new Amount();
        $amountWithoutCurrency->value = 2000;

        yield 'uses payment currency when notification amount currency is null' => [
            'amount' => $amountWithoutCurrency,
            'expectedAmount' => 2000,
            'expectedCurrency' => 'GBP',
        ];

        yield 'uses payment amount and currency when notification amount is null' => [
            'amount' => null,
            'expectedAmount' => 3000,
            'expectedCurrency' => 'PLN',
        ];
    }

    #[DataProvider('provideRefundNotCreatedScenarios')]
    public function testInvokeDoesNotCreateRefundWhenValidationFails(
        array $paymentDetails,
        string $originalReference,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $amount = new Amount();
        $amount->value = 1000;
        $amount->currency = 'EUR';

        $notificationData = new NotificationItemData();
        $notificationData->amount = $amount;
        $notificationData->pspReference = 'TEST_PSP_REF_123';
        $notificationData->originalReference = $originalReference;

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

        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn($paymentDetails);

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
        $this->stateMachine->expects($this->never())
            ->method('can');
        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->handler->__invoke($command);
    }

    public static function provideRefundNotCreatedScenarios(): iterable
    {
        yield 'returns null when payment details missing pspReference' => [
            'paymentDetails' => [],
            'originalReference' => 'ORIGINAL_PSP_REF',
        ];

        yield 'returns null when originalReference does not match payment pspReference' => [
            'paymentDetails' => ['pspReference' => 'DIFFERENT_PSP_REF'],
            'originalReference' => 'ORIGINAL_PSP_REF',
        ];
    }

    #[DataProvider('provideStateTransitionScenarios')]
    public function testInvokeHandlesStateTransitions(
        bool $canTransition,
    ): void {
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
        $notificationData->pspReference = 'TEST_PSP_REF';
        $notificationData->originalReference = 'ORIGINAL_PSP_REF';

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
            ->willThrowException(new \Exception('Reference not found'));

        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn(['pspReference' => 'ORIGINAL_PSP_REF']);

        $this->refundPaymentFactory->expects($this->once())
            ->method('createWithData')
            ->willReturn($refundPayment);

        $this->referenceFactory->expects($this->once())
            ->method('createForRefund')
            ->willReturn($adyenReference);

        $this->referenceRepository->expects($this->once())
            ->method('add');

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with($refundPayment, RefundPaymentTransitions::GRAPH, AdyenRefundPaymentTransitions::TRANSITION_CONFIRM)
            ->willReturn($canTransition);

        if ($canTransition) {
            $this->stateMachine->expects($this->once())
                ->method('apply')
                ->with($refundPayment, RefundPaymentTransitions::GRAPH, AdyenRefundPaymentTransitions::TRANSITION_CONFIRM);
        } else {
            $this->stateMachine->expects($this->never())
                ->method('apply');
        }

        $this->handler->__invoke($command);
    }

    public static function provideStateTransitionScenarios(): iterable
    {
        yield 'applies transition when state machine allows' => [
            'canTransition' => true,
        ];

        yield 'skips transition when state machine does not allow' => [
            'canTransition' => false,
        ];
    }

    public function testInvokeReturnsEarlyWhenRefundPaymentIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $notificationData = new NotificationItemData();
        $notificationData->pspReference = 'TEST_PSP_REF';
        $notificationData->originalReference = 'WRONG_REF';

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
            ->willThrowException(new \Exception('Reference not found'));

        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn(['pspReference' => 'DIFFERENT_REF']);

        $this->refundPaymentFactory->expects($this->never())
            ->method('createWithData');

        $this->stateMachine->expects($this->never())
            ->method('can');
        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->handler->__invoke($command);
    }
}
