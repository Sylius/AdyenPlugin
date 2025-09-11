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

namespace Tests\Sylius\AdyenPlugin\Functional\OrderProcessing;

use Sylius\AdyenPlugin\Client\ResponseStatus;
use Sylius\AdyenPlugin\Controller\Admin\ReverseOrderPaymentAction;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\Sylius\AdyenPlugin\Functional\AdyenTestCase;

final class PaymentReversalTest extends AdyenTestCase
{
    private ReverseOrderPaymentAction $reverseOrderPaymentAction;

    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();

        $this->reverseOrderPaymentAction = $container->get('sylius_adyen.controller.admin.order_payment.reverse');
    }

    protected function createTestOrder(bool $setup = true): OrderInterface
    {
        $order = parent::createTestOrder($setup);
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

        $order = $this->createTestOrder(false);
        $order->setState(OrderInterface::STATE_NEW);
        $order->setPaymentState(OrderPaymentStates::STATE_AUTHORIZED);
        $payment = $order->getLastPayment();
        $payment->setMethod($nonAdyenPaymentMethod);
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);

        $orderRepository = self::getContainer()->get('sylius.repository.order');
        $orderRepository->add($order);

        $request = new Request();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('is not an Adyen payment.');

        ($this->reverseOrderPaymentAction)((string) $order->getId(), (string) $payment->getId(), $request);

        $reversalRequest = $this->adyenClientStub->getLastReversalRequest();
        self::assertNull($reversalRequest);
    }

    public function testReversalNotInitiatedForAdyenPaymentWithManualCapture(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $payment = $this->testOrder->getLastPayment();
        $request = new Request();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('is not an Adyen payment with automatic capture mode.');

        ($this->reverseOrderPaymentAction)((string) $this->testOrder->getId(), (string) $payment->getId(), $request);

        $reversalRequest = $this->adyenClientStub->getLastReversalRequest();
        self::assertNull($reversalRequest);
    }

    public function testReversalNotInitiatedOnFulfilledOrderWithManualCapture(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_FULFILLED);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);

        $this->getEntityManager()->flush();

        $request = new Request();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('is not an Adyen payment with automatic capture mode.');

        ($this->reverseOrderPaymentAction)((string) $this->testOrder->getId(), (string) $payment->getId(), $request);
    }

    public function testPaymentStateChangesToProcessingReversalOnReverseActionWithAutomaticCapture(): void
    {
        $this->setupOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_FULFILLED);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);

        $this->getEntityManager()->flush();

        $initialDetails = $payment->getDetails();
        self::assertArrayHasKey('pspReference', $initialDetails);
        self::assertEquals('TEST_PSP_REF_123', $initialDetails['pspReference']);

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_999',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $request = new Request();
        ($this->reverseOrderPaymentAction)((string) $this->testOrder->getId(), (string) $payment->getId(), $request);

        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());
    }

    public function testCancellationWebhookAfterReversalInitiatedWithAutomaticCapture(): void
    {
        $this->setupOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_NEW);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_456',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $request = new Request();
        ($this->reverseOrderPaymentAction)((string) $this->testOrder->getId(), (string) $payment->getId(), $request);

        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());

        $this->simulateWebhook($payment, EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND, true, ['modification.action' => EventCodeResolverInterface::MODIFICATION_CANCEL]);
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_CANCELLED, $this->testOrder->getPaymentState());
    }

    public function testRefundWebhookAfterReversalInitiatedWithAutomaticCapture(): void
    {
        $this->setupOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_NEW);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_456',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $request = new Request();
        ($this->reverseOrderPaymentAction)((string) $this->testOrder->getId(), (string) $payment->getId(), $request);

        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());

        $originalPspRef = $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF_123';
        $this->simulateWebhook(
            $payment,
            EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND,
            true,
            ['modification.action' => EventCodeResolverInterface::MODIFICATION_REFUND],
            'REFUND_PSP_REF_456',
            null,
            null,
            $originalPspRef,
        );
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $this->testOrder->getPaymentState());

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $this->testOrder]);
        self::assertCount(1, $refundPayments);

        /** @var RefundPaymentInterface $refundPayment */
        $refundPayment = $refundPayments[0];
        self::assertEquals($this->testOrder->getNumber(), $refundPayment->getOrder()->getNumber());
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

    public function testReversalOnFulfilledOrderKeepsOrderFulfilledWithAutomaticCapture(): void
    {
        $this->setupOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_FULFILLED);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_999',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $request = new Request();
        ($this->reverseOrderPaymentAction)((string) $this->testOrder->getId(), (string) $payment->getId(), $request);

        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_FULFILLED, $this->testOrder->getState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $this->testOrder->getPaymentState());
    }

    public function testCancellationWebhookOnFulfilledOrderKeepsOrderFulfilledWithAutomaticCapture(): void
    {
        $this->setupOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_FULFILLED);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_456',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $request = new Request();
        ($this->reverseOrderPaymentAction)((string) $this->testOrder->getId(), (string) $payment->getId(), $request);

        $entityManager->flush();

        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());

        $this->simulateWebhook($payment, EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND, true, ['modification.action' => EventCodeResolverInterface::MODIFICATION_CANCEL]);
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_FULFILLED, $this->testOrder->getState());
        self::assertEquals(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_CANCELLED, $this->testOrder->getPaymentState());
    }

    public function testRefundWebhookOnFulfilledOrderKeepsOrderFulfilledWithAutomaticCapture(): void
    {
        $this->setupOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_FULFILLED);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_456',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $request = new Request();
        ($this->reverseOrderPaymentAction)((string) $this->testOrder->getId(), (string) $payment->getId(), $request);

        $entityManager->flush();

        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());

        $originalPspRef = $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF_123';
        $this->simulateWebhook(
            $payment,
            EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND,
            true,
            ['modification.action' => EventCodeResolverInterface::MODIFICATION_REFUND],
            'REFUND_PSP_REF_456',
            null,
            null,
            $originalPspRef,
        );
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_FULFILLED, $this->testOrder->getState());
        self::assertEquals(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $this->testOrder->getPaymentState());

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $this->testOrder]);
        self::assertCount(1, $refundPayments);

        /** @var RefundPaymentInterface $refundPayment */
        $refundPayment = $refundPayments[0];
        self::assertEquals($this->testOrder->getNumber(), $refundPayment->getOrder()->getNumber());
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
}
