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

namespace Tests\Sylius\AdyenPlugin\Functional\OrderProcessing\Manual;

use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;
use Sylius\RefundPlugin\Event\UnitRefunded;
use Tests\Sylius\AdyenPlugin\Functional\AdyenTestCase;
use Webmozart\Assert\Assert;

final class RefundingTest extends AdyenTestCase
{
    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();
        $this->setCaptureMode(PaymentCaptureMode::MANUAL);

        $container->set('sylius.email_sender', $this->createMock(SenderInterface::class));
    }

    public function testRefundingManualCapturePaymentCreatesRefundPaymentEntity(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $order->getPaymentState());

        $this->triggerFullRefund($payment, $order);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(1, $refundPayments);

        /** @var RefundPaymentInterface $refundPayment */
        $refundPayment = $refundPayments[0];

        self::assertEquals(RefundPaymentInterface::STATE_NEW, $refundPayment->getState());
        self::assertEquals($payment->getAmount(), $refundPayment->getAmount());
        self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
        self::assertCount(1, $adyenReferences);
        self::assertEquals($payment, $adyenReferences[0]->getPayment());

        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function testFullRefundWithWebhookCompletesRefundPayment(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $this->triggerFullRefund($payment, $order);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(1, $refundPayments);

        /** @var RefundPaymentInterface $refundPayment */
        $refundPayment = $refundPayments[0];

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);

        self::assertCount(1, $adyenReferences);
        $adyenReference = $adyenReferences[0];

        $this->simulateWebhook(
            $payment,
            EventCodeResolverInterface::EVENT_REFUND,
            true,
            [],
            $adyenReference->getPspReference(),
            $order->getNumber(),
            null,
            $payment->getDetails()['pspReference'],
        );

        $this->getEntityManager()->refresh($refundPayment);
        $this->getEntityManager()->refresh($order);

        self::assertEquals(RefundPaymentInterface::STATE_COMPLETED, $refundPayment->getState());

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
        self::assertCount(1, $adyenReferences);
        self::assertEquals($payment, $adyenReferences[0]->getPayment());

        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_REFUNDED, $payment->getState());
    }

    public function testPartialRefundCreatesRefundPaymentEntity(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $partialRefundAmount = $payment->getAmount() - 1000;
        self::assertLessThan($payment->getAmount(), $partialRefundAmount);
        $this->triggerPartialRefund($payment, $order, $partialRefundAmount);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(1, $refundPayments);

        /** @var RefundPaymentInterface $refundPayment */
        $refundPayment = $refundPayments[0];

        self::assertEquals(RefundPaymentInterface::STATE_COMPLETED, $refundPayment->getState());
        self::assertEquals($partialRefundAmount, $refundPayment->getAmount());
        self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
        self::assertCount(1, $adyenReferences);
        self::assertEquals($payment, $adyenReferences[0]->getPayment());

        self::assertEquals(OrderPaymentStates::STATE_PARTIALLY_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function testMultiplePartialRefundsReachingOrderTotal(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $firstRefundAmount = (int) ($payment->getAmount() * 0.3);
        $this->triggerPartialRefund($payment, $order, $firstRefundAmount);

        $secondRefundAmount = $payment->getAmount() - $firstRefundAmount;
        $this->triggerPartialRefund($payment, $order, $secondRefundAmount);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(2, $refundPayments);

        $totalRefundAmount = array_sum(array_map(fn ($refund) => $refund->getAmount(), $refundPayments));
        self::assertEquals($payment->getAmount(), $totalRefundAmount);

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        foreach ($refundPayments as $refundPayment) {
            self::assertEquals(RefundPaymentInterface::STATE_COMPLETED, $refundPayment->getState());
            self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

            $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
            self::assertCount(1, $adyenReferences);
            self::assertEquals($payment, $adyenReferences[0]->getPayment());
        }

        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function testThreeUnequalPartialRefundsReachingOrderTotal(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $firstRefundAmount = (int) ($payment->getAmount() * 0.25);
        $this->triggerPartialRefund($payment, $order, $firstRefundAmount);

        $secondRefundAmount = (int) ($payment->getAmount() * 0.35);
        $this->triggerPartialRefund($payment, $order, $secondRefundAmount);

        $thirdRefundAmount = $payment->getAmount() - $firstRefundAmount - $secondRefundAmount;
        $this->triggerPartialRefund($payment, $order, $thirdRefundAmount);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(3, $refundPayments);

        $totalRefundAmount = array_sum(array_map(fn ($refund) => $refund->getAmount(), $refundPayments));
        self::assertEquals($payment->getAmount(), $totalRefundAmount);

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        foreach ($refundPayments as $refundPayment) {
            self::assertEquals(RefundPaymentInterface::STATE_COMPLETED, $refundPayment->getState());
            self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

            $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
            self::assertCount(1, $adyenReferences);
            self::assertEquals($payment, $adyenReferences[0]->getPayment());
        }

        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function testFiveSmallPartialRefundsReachingOrderTotal(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $refundTotal = $payment->getAmount();

        $refundAmounts = [
            (int) ($refundTotal * 0.15),
            (int) ($refundTotal * 0.25),
            (int) ($refundTotal * 0.20),
            (int) ($refundTotal * 0.30),
        ];

        $refundAmounts[] = $refundTotal - array_sum($refundAmounts);
        $expectedRefundCount = count($refundAmounts);

        foreach ($refundAmounts as $refundAmount) {
            self::assertGreaterThan(0, $refundAmount);
            $this->triggerPartialRefund($payment, $order, $refundAmount);
        }

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount($expectedRefundCount, $refundPayments);

        $totalRefundAmount = array_sum(array_map(fn ($refund) => $refund->getAmount(), $refundPayments));
        self::assertEquals($payment->getAmount(), $totalRefundAmount);

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        foreach ($refundPayments as $refundPayment) {
            self::assertEquals(RefundPaymentInterface::STATE_COMPLETED, $refundPayment->getState());
            self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());
            self::assertGreaterThan(0, $refundPayment->getAmount());

            $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
            self::assertCount(1, $adyenReferences);
            self::assertEquals($payment, $adyenReferences[0]->getPayment());
        }

        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function testPartialRefundThenFullRefund(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $partialRefundAmount = (int) ($payment->getAmount() * 0.3);
        self::assertLessThan($payment->getAmount(), $partialRefundAmount);
        $this->triggerPartialRefund($payment, $order, $partialRefundAmount);

        self::assertEquals(OrderPaymentStates::STATE_PARTIALLY_REFUNDED, $order->getPaymentState());

        $remainingAmount = $payment->getAmount() - $partialRefundAmount;
        $this->triggerPartialRefund($payment, $order, $remainingAmount);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(2, $refundPayments);

        $totalRefundAmount = array_sum(array_map(fn ($refund) => $refund->getAmount(), $refundPayments));
        self::assertEquals($payment->getAmount(), $totalRefundAmount);

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        foreach ($refundPayments as $refundPayment) {
            self::assertEquals(RefundPaymentInterface::STATE_COMPLETED, $refundPayment->getState());
            self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

            $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
            self::assertCount(1, $adyenReferences);
            self::assertEquals($payment, $adyenReferences[0]->getPayment());
        }

        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    private function capturePaymentAndCompleteOrder(PaymentInterface $payment, OrderInterface $order): void
    {
        $captureOrderPaymentAction = $this->getCaptureOrderPaymentAction();
        $captureOrderPaymentAction->__invoke(
            (string) $order->getId(),
            (string) $payment->getId(),
            $this->createRequest(),
        );

        $this->simulateWebhook($payment, EventCodeResolverInterface::EVENT_CAPTURE, true);

        $this->getEntityManager()->flush();
        $this->getEntityManager()->refresh($payment);
        $this->getEntityManager()->refresh($order);

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
    }

    private function triggerFullRefund(PaymentInterface $payment, OrderInterface $order): void
    {
        $container = self::getContainer();
        $entityManager = $this->getEntityManager();

        $refundPaymentFactory = $container->get('sylius_refund.factory.refund_payment');
        $refundPayment = $refundPaymentFactory->createWithData(
            $order,
            $payment->getAmount(),
            $order->getCurrencyCode(),
            RefundPaymentInterface::STATE_NEW,
            $payment->getMethod(),
        );

        $entityManager->persist($refundPayment);
        $entityManager->flush();

        $refundPaymentGeneratedEvent = new RefundPaymentGenerated(
            $refundPayment->getId(),
            $order->getNumber(),
            $payment->getAmount(),
            $order->getCurrencyCode(),
            $payment->getMethod()->getId(),
            $payment->getId(),
        );

        $eventBus = $container->get('sylius.event_bus');
        $eventBus->dispatch($refundPaymentGeneratedEvent);

        $order->setPaymentState(OrderPaymentStates::STATE_REFUNDED);
        $entityManager->flush();
    }

    private function triggerPartialRefund(PaymentInterface $payment, OrderInterface $order, int $refundAmount): void
    {
        $container = self::getContainer();
        $entityManager = $this->getEntityManager();

        $refundPaymentFactory = $container->get('sylius_refund.factory.refund_payment');
        $refundPayment = $refundPaymentFactory->createWithData(
            $order,
            $refundAmount,
            $order->getCurrencyCode(),
            RefundPaymentInterface::STATE_NEW,
            $payment->getMethod(),
        );

        $entityManager->persist($refundPayment);
        $entityManager->flush();

        $refundPaymentGeneratedEvent = new RefundPaymentGenerated(
            $refundPayment->getId(),
            $order->getNumber(),
            $refundAmount,
            $order->getCurrencyCode(),
            $payment->getMethod()->getId(),
            $payment->getId(),
        );

        $eventBus = $container->get('sylius.event_bus');
        $eventBus->dispatch($refundPaymentGeneratedEvent);

        $adyenReferenceRepository = $container->get('sylius_adyen.repository.adyen_reference');
        $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
        Assert::notEmpty($adyenReferences, 'Expected AdyenReference to be created for refund payment');

        $adyenReference = $adyenReferences[0];

        $this->simulateWebhook(
            $payment,
            EventCodeResolverInterface::EVENT_REFUND,
            true,
            [],
            $adyenReference->getPspReference(),
            $order->getNumber(),
            null,
            $payment->getDetails()['pspReference'],
        );

        $entityManager->refresh($refundPayment);
        $entityManager->refresh($order);

        $refundPaymentRepository = $container->get('sylius_refund.repository.refund_payment');
        $allRefundPayments = $refundPaymentRepository->findBy(['order' => $order]);
        $totalRefundAmount = array_sum(array_map(fn ($refund) => $refund->getAmount(), $allRefundPayments));

        if ($totalRefundAmount >= $payment->getAmount()) {
            $orderFullyRefundedStateResolver = $container->get('sylius_refund.state_resolver.order_fully_refunded');
            $orderFullyRefundedStateResolver->resolve($order->getNumber());
        } else {
            $unitRefundedEvent = new UnitRefunded(
                $order->getNumber(),
                999,
                $refundAmount,
            );

            $eventBus->dispatch($unitRefundedEvent);
        }
    }
}
