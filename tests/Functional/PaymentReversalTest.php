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

namespace Tests\Sylius\AdyenPlugin\Functional;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\AdyenPlugin\Client\ResponseStatus;
use Sylius\AdyenPlugin\Controller\Shop\PaymentsAction;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\Amount;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\OrderTransitions;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class PaymentReversalTest extends AdyenTestCase
{
    private PaymentsAction $paymentsAction;

    private MessageBusInterface $messageBus;

    private PaymentCommandFactoryInterface $paymentCommandFactory;

    private StateMachineInterface $stateMachine;

    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();

        $this->paymentsAction = $this->getPaymentsAction();
        $this->messageBus = $container->get('sylius.command_bus');
        $this->paymentCommandFactory = $container->get('sylius_adyen.bus.payment_command_factory');
        $this->stateMachine = $container->get('sylius_abstraction.state_machine');
    }

    protected function createTestOrder(): OrderInterface
    {
        $order = parent::createTestOrder();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);

        return $order;
    }

    public function testReversalNotInitiatedForNonAdyenPayment(): void
    {
        $nonAdyenPaymentMethod = new PaymentMethod();
        $nonAdyenPaymentMethod->setCode('bank_transfer');
        $nonAdyenPaymentMethod->setCurrentLocale('en_US');
        $nonAdyenPaymentMethod->setFallbackLocale('en_US');
        $nonAdyenPaymentMethod->setName('Bank Transfer');

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('offline');
        $gatewayConfig->setGatewayName('bank_transfer');
        $gatewayConfig->setConfig([]);

        $nonAdyenPaymentMethod->setGatewayConfig($gatewayConfig);

        $paymentMethodRepository = self::getContainer()->get('sylius.repository.payment_method');
        $paymentMethodRepository->add($nonAdyenPaymentMethod);

        $order = $this->createTestOrder();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setPaymentState(OrderPaymentStates::STATE_AUTHORIZED);
        $payment = $order->getLastPayment();
        $payment->setMethod($nonAdyenPaymentMethod);
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);

        $orderRepository = self::getContainer()->get('sylius.repository.order');
        $orderRepository->add($order);

        $this->stateMachine->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        self::assertEquals(OrderInterface::STATE_CANCELLED, $order->getState());
        self::assertEquals(PaymentInterface::STATE_CANCELLED, $payment->getState());

        $reversalRequest = $this->adyenClientStub->getLastReversalRequest();
        self::assertNull($reversalRequest);
    }

    public function testPaymentStateChangesToProcessingReversalOnCancelTransition(): void
    {
        $this->setupPaidOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        $initialDetails = $payment->getDetails();

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertArrayHasKey('pspReference', $initialDetails);
        self::assertEquals('TEST_PSP_REF_123', $initialDetails['pspReference']);

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_999',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $this->stateMachine->apply($this->testOrder, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());
    }

    public function testCancellationWebhookAfterReversalInitiated(): void
    {
        $this->setupPaidOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_456',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $this->stateMachine->apply($this->testOrder, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());

        $this->simulateWebhook($payment, 'cancellation');
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_CANCELLED, $this->testOrder->getPaymentState());
    }

    public function testRefundWebhookAfterReversalInitiated(): void
    {
        $this->setupPaidOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_456',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $this->stateMachine->apply($this->testOrder, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());

        $this->simulateWebhook($payment, 'refund');
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $this->testOrder->getPaymentState());

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $this->testOrder]);
        self::assertCount(1, $refundPayments);

        /** @var RefundPaymentInterface $refundPayment */
        $refundPayment = $refundPayments[0];
        self::assertEquals($this->testOrder->getNumber(), $refundPayment->getOrderNumber());
        self::assertEquals($payment->getAmount(), $refundPayment->getAmount());
        self::assertEquals($payment->getCurrencyCode(), $refundPayment->getCurrencyCode());
        self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

        $adyenReferences = $this->adyenReferenceRepository->findBy(['refundPayment' => $refundPayment]);
        self::assertCount(1, $adyenReferences);

        /** @var AdyenReferenceInterface $adyenReference */
        $adyenReference = $adyenReferences[0];
        self::assertEquals($payment, $adyenReference->getPayment());
        self::assertEquals($refundPayment, $adyenReference->getRefundPayment());
        self::assertNotNull($adyenReference->getPspReference());
    }

    private function setupPaidOrderWithAdyenPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Authorised',
            'pspReference' => 'TEST_PSP_REF_123',
            'merchantReference' => $this->testOrder->getNumber(),
        ]);

        $request = $this->createRequest([
            'paymentMethod' => [
                'type' => 'scheme',
                'encryptedCardNumber' => 'test_encrypted_number',
                'encryptedExpiryMonth' => 'test_encrypted_month',
                'encryptedExpiryYear' => 'test_encrypted_year',
                'encryptedSecurityCode' => 'test_encrypted_cvv',
            ],
        ]);

        $response = ($this->paymentsAction)($request);
        self::assertEquals(200, $response->getStatusCode());

        $payment = $this->testOrder->getLastPayment();
        $this->simulateWebhook($payment, 'authorisation');

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());

        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);
        $entityManager->flush();
    }

    private function simulateWebhook(PaymentInterface $payment, string $eventCode): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = $eventCode;
        $notificationData->success = true;
        $notificationData->merchantReference = $payment->getOrder()?->getNumber() ?? 'TEST_ORDER';
        $notificationData->paymentMethod = 'scheme';

        if ($eventCode === 'refund') {
            $notificationData->pspReference = 'REFUND_PSP_REF_456';
            $notificationData->originalReference = $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF';

            $amountData = new Amount();
            $amountData->value = $payment->getAmount();
            $amountData->currency = $payment->getCurrencyCode();
            $notificationData->amount = $amountData;
        } else {
            $notificationData->pspReference = $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF';
        }

        $command = $this->paymentCommandFactory->createForEvent(
            $eventCode,
            $payment,
            $notificationData,
        );

        $this->messageBus->dispatch($command);
    }
}
