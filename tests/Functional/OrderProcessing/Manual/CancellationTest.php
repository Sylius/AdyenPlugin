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

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Sylius\Component\Order\OrderTransitions;
use Tests\Sylius\AdyenPlugin\Functional\AdyenTestCase;

final class CancellationTest extends AdyenTestCase
{
    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();
        $this->setCaptureMode(PaymentCaptureMode::MANUAL);

        $container->set('sylius.email_sender', $this->createMock(SenderInterface::class));
    }

    public function testCancellingOrderWithAuthorizedPaymentInManualCaptureMode(): void
    {
        $order = $this->setupOrderWithAuthorizedPaymentAndCancel();
        $payment = $order->getLastPayment();

        self::assertEquals(OrderInterface::STATE_CANCELLED, $order->getState());
        self::assertEquals(OrderPaymentStates::STATE_AUTHORIZED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());
    }

    #[DataProvider('cancellationWebhookProvider')]
    public function testCancellationWebhook(
        string $initialPaymentState,
        string $initialOrderState,
        string $initialOrderPaymentState,
        array $paymentDetails,
        string $expectedPaymentState,
        string $expectedOrderState,
        string $expectedOrderPaymentState,
        string $description,
    ): void {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        $payment->setState($initialPaymentState);
        $order->setState($initialOrderState);
        $order->setPaymentState($initialOrderPaymentState);

        if ([] !== $paymentDetails) {
            $details = $payment->getDetails();
            foreach ($paymentDetails as $key => $value) {
                $details[$key] = $value;
            }
            $payment->setDetails($details);
        }

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        $this->simulateWebhook(
            $payment,
            EventCodeResolverInterface::EVENT_CANCELLATION,
        );

        $entityManager->flush();

        self::assertEquals($expectedPaymentState, $payment->getState(), "Payment state mismatch for: {$description}");
        self::assertEquals($expectedOrderState, $order->getState(), "Order state mismatch for: {$description}");
        self::assertEquals($expectedOrderPaymentState, $order->getPaymentState(), "Order payment state mismatch for: {$description}");
    }

    public static function cancellationWebhookProvider(): iterable
    {
        yield 'Cancellation webhook with PROCESSING payment state' => [
            'initialPaymentState' => PaymentInterface::STATE_PROCESSING,
            'initialOrderState' => OrderInterface::STATE_CANCELLED,
            'initialOrderPaymentState' => OrderPaymentStates::STATE_AUTHORIZED,
            'paymentDetails' => [CancelPayment::PROCESSING_CANCELLATION => true],
            'expectedPaymentState' => PaymentInterface::STATE_CANCELLED,
            'expectedOrderState' => OrderInterface::STATE_CANCELLED,
            'expectedOrderPaymentState' => OrderPaymentStates::STATE_CANCELLED,
            'description' => 'Processing payment with cancellation flag transitions to cancelled',
        ];

        yield 'Cancellation webhook with PROCESSING payment state with no processing flag' => [
            'initialPaymentState' => PaymentInterface::STATE_PROCESSING,
            'initialOrderState' => OrderInterface::STATE_CANCELLED,
            'initialOrderPaymentState' => OrderPaymentStates::STATE_AUTHORIZED,
            'paymentDetails' => [],
            'expectedPaymentState' => PaymentInterface::STATE_PROCESSING,
            'expectedOrderState' => OrderInterface::STATE_CANCELLED,
            'expectedOrderPaymentState' => OrderPaymentStates::STATE_AUTHORIZED,
            'description' => 'Processing payment with cancellation flag transitions to cancelled',
        ];

        yield 'Cancellation webhook with NEW payment state' => [
            'initialPaymentState' => PaymentInterface::STATE_NEW,
            'initialOrderState' => OrderInterface::STATE_NEW,
            'initialOrderPaymentState' => OrderPaymentStates::STATE_AWAITING_PAYMENT,
            'paymentDetails' => [],
            'expectedPaymentState' => PaymentInterface::STATE_NEW,
            'expectedOrderState' => OrderInterface::STATE_NEW,
            'expectedOrderPaymentState' => OrderPaymentStates::STATE_AWAITING_PAYMENT,
            'description' => 'NEW payment cannot transition to cancelled',
        ];

        yield 'Cancellation webhook with COMPLETED payment state' => [
            'initialPaymentState' => PaymentInterface::STATE_COMPLETED,
            'initialOrderState' => OrderInterface::STATE_NEW,
            'initialOrderPaymentState' => OrderPaymentStates::STATE_PAID,
            'paymentDetails' => [],
            'expectedPaymentState' => PaymentInterface::STATE_COMPLETED,
            'expectedOrderState' => OrderInterface::STATE_NEW,
            'expectedOrderPaymentState' => OrderPaymentStates::STATE_PAID,
            'description' => 'Completed payment should not be cancelled',
        ];

        yield 'Cancellation webhook with FAILED payment state' => [
            'initialPaymentState' => PaymentInterface::STATE_FAILED,
            'initialOrderState' => OrderInterface::STATE_NEW,
            'initialOrderPaymentState' => OrderPaymentStates::STATE_AWAITING_PAYMENT,
            'paymentDetails' => [],
            'expectedPaymentState' => PaymentInterface::STATE_FAILED,
            'expectedOrderState' => OrderInterface::STATE_NEW,
            'expectedOrderPaymentState' => OrderPaymentStates::STATE_AWAITING_PAYMENT,
            'description' => 'Failed payment state should remain unchanged',
        ];

        yield 'Cancellation webhook with FULFILLED order state' => [
            'initialPaymentState' => PaymentInterface::STATE_COMPLETED,
            'initialOrderState' => OrderInterface::STATE_FULFILLED,
            'initialOrderPaymentState' => OrderPaymentStates::STATE_PAID,
            'paymentDetails' => [],
            'expectedPaymentState' => PaymentInterface::STATE_COMPLETED,
            'expectedOrderState' => OrderInterface::STATE_FULFILLED,
            'expectedOrderPaymentState' => OrderPaymentStates::STATE_PAID,
            'description' => 'Fulfilled order should not be affected by cancellation',
        ];
    }

    protected function setupOrderWithAuthorizedPaymentAndCancel(): OrderInterface
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $order = $this->testOrder;
        $payment = $order->getLastPayment();

        self::assertEquals(OrderInterface::STATE_NEW, $order->getState());
        self::assertEquals(OrderPaymentStates::STATE_AUTHORIZED, $order->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_AUTHORIZED, $payment->getState());

        $this->stateMachine->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        return $order;
    }
}
