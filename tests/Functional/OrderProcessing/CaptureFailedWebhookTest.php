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

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Tests\Sylius\AdyenPlugin\Functional\AdyenTestCase;

final class CaptureFailedWebhookTest extends AdyenTestCase
{
    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();
        $container->set('sylius.email_sender', $this->createMock(SenderInterface::class));
    }

    #[DataProvider('captureFailedScenarios')]
    public function testCaptureFailedWebhookBehavior(
        string $captureMode,
        string $initialOrderState,
        string $initialPaymentState,
        string $initialOrderPaymentState,
    ): void {
        $this->setupOrderWithAdyenPayment($captureMode);

        $payment = $this->testOrder->getLastPayment();
        $order = $this->testOrder;
        $originalAmount = $payment->getAmount();

        $payment->setState($initialPaymentState);
        $order->setState($initialOrderState);
        $order->setPaymentState($initialOrderPaymentState);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        self::assertCount(1, $order->getPayments());

        $this->simulateWebhook($payment, EventCodeResolverInterface::EVENT_CAPTURE_FAILED);

        $entityManager->flush();
        $entityManager->refresh($order);

        self::assertEquals($initialOrderState, $order->getState());
        self::assertCount(2, $order->getPayments());

        $failedPayment = $order->getLastPayment(PaymentInterface::STATE_FAILED);
        self::assertNotNull($failedPayment);

        $newPayment = $order->getLastPayment(PaymentInterface::STATE_NEW);
        self::assertNotNull($newPayment);

        self::assertEquals($originalAmount, $newPayment->getAmount());
        self::assertEquals($payment->getCurrencyCode(), $newPayment->getCurrencyCode());
        self::assertEquals($payment->getMethod(), $newPayment->getMethod());

        self::assertEquals(OrderPaymentStates::STATE_AWAITING_PAYMENT, $order->getPaymentState());
    }

    public static function captureFailedScenarios(): array
    {
        return [
            'authorized payment automatic' => [
                'captureMode' => PaymentCaptureMode::AUTOMATIC,
                'initialOrderState' => OrderInterface::STATE_NEW,
                'initialPaymentState' => PaymentInterface::STATE_AUTHORIZED,
                'initialOrderPaymentState' => OrderPaymentStates::STATE_AUTHORIZED,
            ],
            'authorized payment manual' => [
                'captureMode' => PaymentCaptureMode::MANUAL,
                'initialOrderState' => OrderInterface::STATE_NEW,
                'initialPaymentState' => PaymentInterface::STATE_AUTHORIZED,
                'initialOrderPaymentState' => OrderPaymentStates::STATE_AUTHORIZED,
            ],
            'paid order automatic' => [
                'captureMode' => PaymentCaptureMode::AUTOMATIC,
                'initialOrderState' => OrderInterface::STATE_NEW,
                'initialPaymentState' => PaymentInterface::STATE_COMPLETED,
                'initialOrderPaymentState' => OrderPaymentStates::STATE_PAID,
            ],
            'paid order manual' => [
                'captureMode' => PaymentCaptureMode::MANUAL,
                'initialOrderState' => OrderInterface::STATE_NEW,
                'initialPaymentState' => PaymentInterface::STATE_COMPLETED,
                'initialOrderPaymentState' => OrderPaymentStates::STATE_PAID,
            ],
            'fulfilled order automatic' => [
                'captureMode' => PaymentCaptureMode::AUTOMATIC,
                'initialOrderState' => OrderInterface::STATE_FULFILLED,
                'initialPaymentState' => PaymentInterface::STATE_COMPLETED,
                'initialOrderPaymentState' => OrderPaymentStates::STATE_PAID,
            ],
            'fulfilled order manual' => [
                'captureMode' => PaymentCaptureMode::MANUAL,
                'initialOrderState' => OrderInterface::STATE_FULFILLED,
                'initialPaymentState' => PaymentInterface::STATE_COMPLETED,
                'initialOrderPaymentState' => OrderPaymentStates::STATE_PAID,
            ],
        ];
    }
}
