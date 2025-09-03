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

use Sylius\AdyenPlugin\Bus\Command\RequestCapture;
use Sylius\AdyenPlugin\Controller\Admin\CaptureOrderPaymentAction;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Tests\Sylius\AdyenPlugin\Functional\AdyenTestCase;

final class ManualCaptureTest extends AdyenTestCase
{
    private CaptureOrderPaymentAction $captureOrderPaymentAction;

    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();
        $this->setCaptureMode(PaymentCaptureMode::MANUAL);

        $this->captureOrderPaymentAction = $this->getCaptureOrderPaymentAction();

        $container->set('sylius.email_sender', $this->createMock(SenderInterface::class));
    }

    public function testManuallyCapturingPaymentPutsItInProcessingState(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $payment = $this->testOrder->getLastPayment();

        $container = self::getContainer();
        $messageBusSpy = $container->get('sylius_adyen.test.message_bus_spy');
        $messageBusSpy->clearDispatchedMessages();

        $this->captureOrderPaymentAction->__invoke(
            (string) $this->testOrder->getId(),
            (string) $payment->getId(),
            $this->createRequest(),
        );

        $dispatchedMessages = $messageBusSpy->getDispatchedMessages();
        $captureCommands = array_filter($dispatchedMessages, function ($message) {
            return $message instanceof RequestCapture;
        });

        self::assertCount(1, $captureCommands, 'Expected exactly one RequestCapture command to be dispatched');

        $captureCommand = reset($captureCommands);
        self::assertInstanceOf(RequestCapture::class, $captureCommand);
        self::assertSame($payment->getOrder(), $captureCommand->getOrder());

        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());
        self::assertArrayHasKey('resultCode', $payment->getDetails());
        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);
        self::assertArrayHasKey('pspReference', $payment->getDetails());
        self::assertEquals('TEST_PSP_REF_123', $payment->getDetails()['pspReference']);
    }

    public function testManualCaptureWithWebhookCompletesPaymentAndPaysTheOrder(): void
    {
        $this->setupOrderWithAdyenPayment(PaymentCaptureMode::MANUAL);

        $payment = $this->testOrder->getLastPayment();
        $order = $this->testOrder;

        $container = self::getContainer();
        $messageBusSpy = $container->get('sylius_adyen.test.message_bus_spy');
        $messageBusSpy->clearDispatchedMessages();

        $this->captureOrderPaymentAction->__invoke(
            (string) $order->getId(),
            (string) $payment->getId(),
            $this->createRequest(),
        );

        $dispatchedMessages = $messageBusSpy->getDispatchedMessages();
        $captureCommands = array_filter($dispatchedMessages, function ($message) {
            return $message instanceof RequestCapture;
        });

        self::assertCount(1, $captureCommands, 'Expected exactly one RequestCapture command to be dispatched');
        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());

        $this->simulateWebhook($payment, EventCodeResolverInterface::EVENT_CAPTURE, true);

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $order->getPaymentState());
    }
}
