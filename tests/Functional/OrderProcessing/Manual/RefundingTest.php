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
use Sylius\Component\Core\Model\OrderItemUnitInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\RefundPlugin\Command\RefundUnits;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Event\UnitRefunded;
use Sylius\RefundPlugin\Model\OrderItemUnitRefund;
use Symfony\Component\Messenger\MessageBusInterface;
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

    public function testPartialRefund(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $order->getPaymentState());

        $partialRefundAmount = (int) ($payment->getAmount() * 0.5);
        self::assertLessThan($payment->getAmount(), $partialRefundAmount);

        $refundPayment = $this->createRefundPayment($payment, $order, $partialRefundAmount);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(1, $refundPayments);
        self::assertEquals(RefundPaymentInterface::STATE_NEW, $refundPayment->getState());
        self::assertEquals($partialRefundAmount, $refundPayment->getAmount());
        self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
        self::assertCount(1, $adyenReferences);
        self::assertEquals($payment, $adyenReferences[0]->getPayment());

        self::assertEquals(OrderPaymentStates::STATE_PARTIALLY_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function testPartialRefundWithWebhook(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $partialRefundAmount = (int) ($payment->getAmount() * 0.5);
        self::assertLessThan($payment->getAmount(), $partialRefundAmount);

        $refundPayment = $this->createRefundPayment($payment, $order, $partialRefundAmount);
        $this->simulateRefundWebhook($payment, $order, $refundPayment);

        $this->getEntityManager()->refresh($refundPayment);
        $this->getEntityManager()->refresh($order);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(1, $refundPayments);
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

    public function testFullRefund(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $refundPayment = $this->createRefundPayment($payment, $order, $payment->getAmount());

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(1, $refundPayments);
        self::assertEquals(RefundPaymentInterface::STATE_NEW, $refundPayment->getState());
        self::assertEquals($payment->getAmount(), $refundPayment->getAmount());
        self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
        self::assertCount(1, $adyenReferences);
        self::assertEquals($payment, $adyenReferences[0]->getPayment());

        self::assertEquals(OrderPaymentStates::STATE_PARTIALLY_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function testFullRefundWithWebhook(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $refundPayment = $this->createRefundPayment($payment, $order, $payment->getAmount());
        $this->simulateRefundWebhook($payment, $order, $refundPayment);

        $this->getEntityManager()->refresh($refundPayment);
        $this->getEntityManager()->refresh($order);
        $this->getEntityManager()->refresh($payment);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(1, $refundPayments);
        self::assertEquals(RefundPaymentInterface::STATE_COMPLETED, $refundPayment->getState());
        self::assertEquals($payment->getAmount(), $refundPayment->getAmount());
        self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
        self::assertCount(1, $adyenReferences);
        self::assertEquals($payment, $adyenReferences[0]->getPayment());

        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_REFUNDED, $payment->getState());
    }

    public function testMultiplePartialRefundsWithWebhookReachingOrderTotal(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $this->capturePaymentAndCompleteOrder($payment, $order);

        $totalAmount = $payment->getAmount();

        $refundAmounts = [
            (int) ($totalAmount * 0.25),  // 25%
            (int) ($totalAmount * 0.35),  // 35%
            (int) ($totalAmount * 0.15),  // 15%
        ];
        $refundAmounts[] = $totalAmount - array_sum($refundAmounts);

        foreach ($refundAmounts as $index => $refundAmount) {
            $refundPayment = $this->createRefundPayment($payment, $order, $refundAmount);
            $this->simulateRefundWebhook($payment, $order, $refundPayment);

            $this->getEntityManager()->refresh($refundPayment);
            $this->getEntityManager()->refresh($order);

            if ($index < count($refundAmounts) - 1) {
                self::assertEquals(
                    OrderPaymentStates::STATE_PARTIALLY_REFUNDED,
                    $order->getPaymentState(),
                    sprintf('Order should be partially_refunded after refund %d of %d', $index + 1, count($refundAmounts)),
                );
            }
        }

        $this->getEntityManager()->refresh($order);
        $this->getEntityManager()->refresh($payment);

        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $allRefundPayments = $refundPaymentRepository->findBy(['order' => $order]);

        self::assertCount(4, $allRefundPayments);

        $totalRefundedAmount = 0;
        foreach ($allRefundPayments as $refundPayment) {
            self::assertEquals(RefundPaymentInterface::STATE_COMPLETED, $refundPayment->getState());
            self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());
            $totalRefundedAmount += $refundPayment->getAmount();

            $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
            $adyenReferences = $adyenReferenceRepository->findByRefundPayment($refundPayment);
            self::assertCount(1, $adyenReferences);
            self::assertEquals($payment, $adyenReferences[0]->getPayment());
        }

        self::assertEquals($totalAmount, $totalRefundedAmount);

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

    private function createRefundPayment(
        PaymentInterface $payment,
        OrderInterface $order,
        int $refundAmount,
    ): RefundPaymentInterface {
        $container = self::getContainer();

        // Get order item units to refund
        $unitsToRefund = [];
        $remainingAmount = $refundAmount;

        /** @var OrderItemUnitInterface[] $units */
        $units = [];
        foreach ($order->getItems() as $item) {
            foreach ($item->getUnits() as $unit) {
                $units[] = $unit;
            }
        }

        // Calculate how much to refund from each unit
        foreach ($units as $unit) {
            if ($remainingAmount <= 0) {
                break;
            }

            $unitTotal = $unit->getTotal();
            $refundForThisUnit = min($unitTotal, $remainingAmount);

            if ($refundForThisUnit > 0) {
                $unitsToRefund[] = new OrderItemUnitRefund($unit->getId(), $refundForThisUnit);
                $remainingAmount -= $refundForThisUnit;
            }
        }

        // Dispatch RefundUnits command
        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get('messenger.default_bus');

        $command = new RefundUnits(
            $order->getNumber(),
            $unitsToRefund,
            $payment->getMethod()->getId(),
            'Test refund',
        );

        $commandBus->dispatch($command);

        // Get the created refund payment
        $refundPaymentRepository = $container->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $order], ['id' => 'DESC']);

        Assert::notEmpty($refundPayments, 'Expected refund payment to be created');

        return $refundPayments[0];
    }

    private function simulateRefundWebhook(
        PaymentInterface $payment,
        OrderInterface $order,
        RefundPaymentInterface $refundPayment,
    ): void {
        $container = self::getContainer();
        $entityManager = $this->getEntityManager();

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
                $refundPayment->getAmount(),
            );

            $eventBus = $container->get('sylius.event_bus');
            $eventBus->dispatch($unitRefundedEvent);
        }
    }
}
