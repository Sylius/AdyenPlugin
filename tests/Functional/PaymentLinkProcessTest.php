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

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\AdyenPlugin\Controller\Admin\GeneratePayLinkAction;
use Sylius\AdyenPlugin\Controller\Shop\ProcessNotificationsAction;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Entity\PaymentLink;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Mime\Email;

final class PaymentLinkProcessTest extends AdyenTestCase
{
    private ProcessNotificationsAction $processNotificationsAction;

    private GeneratePayLinkAction $generatePayLinkAction;

    protected function initializeServices($container): void
    {
        $this->processNotificationsAction = $this->getProcessNotificationsAction();
        $this->generatePayLinkAction = $this->getGeneratePayLinkAction();
    }

    #[DataProvider('provideCaptureModesForGeneration')]
    public function testPaymentLinkGeneration(string $captureMode): void
    {
        $this->setCaptureMode($captureMode);

        $paymentLinkId = 'PL_TEST_GENERATED_123456';
        $paymentLinkUrl = 'https://test.adyen.link/PL_TEST_GENERATED_123456';

        $this->testOrder->setState(OrderInterface::STATE_NEW);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_NEW);

        $entityManager = $this->getEntityManager();
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_AWAITING_PAYMENT, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());

        $paymentLinks = $this->paymentLinkRepository->findBy(['payment' => $payment]);
        self::assertCount(0, $paymentLinks);

        $this->adyenClientStub->setPaymentLinkResponse([
            'id' => $paymentLinkId,
            'url' => $paymentLinkUrl,
            'expiresAt' => '2024-12-31T23:59:59Z',
            'reference' => $this->testOrder->getNumber(),
            'amount' => [
                'value' => $payment->getAmount(),
                'currency' => $payment->getCurrencyCode(),
            ],
            'merchantAccount' => 'test_merchant',
            'status' => 'active',
        ]);

        $request = $this->createRequest();
        $response = ($this->generatePayLinkAction)((string) $payment->getId(), $request);

        self::assertInstanceOf(RedirectResponse::class, $response);

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_AWAITING_PAYMENT, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());

        $createdPaymentLinks = $this->paymentLinkRepository->findBy(['payment' => $payment]);
        self::assertCount(1, $createdPaymentLinks, 'A new PaymentLink should be created');

        /** @var PaymentLinkInterface $paymentLink */
        $paymentLink = $createdPaymentLinks[0];
        self::assertEquals($paymentLinkId, $paymentLink->getPaymentLinkId());
        self::assertEquals($paymentLinkUrl, $paymentLink->getPaymentLinkUrl());
        self::assertEquals($payment, $paymentLink->getPayment());

        $paymentDetails = $payment->getDetails();
        self::assertArrayHasKey('id', $paymentDetails);
        self::assertEquals($paymentLinkId, $paymentDetails['id']);
        self::assertArrayHasKey('url', $paymentDetails);
        self::assertEquals($paymentLinkUrl, $paymentDetails['url']);
        self::assertArrayHasKey('status', $paymentDetails);
        self::assertEquals('active', $paymentDetails['status']);

        /** @var Email $message */
        $message = self::getMailerMessage();
        self::assertNotNull($message, 'An email should be sent');

        $expectedEmail = $this->testOrder->getCustomer()->getEmail();
        $recipients = $message->getTo();
        self::assertCount(1, $recipients);
        self::assertEquals($expectedEmail, $recipients[0]->getAddress());

        $emailContent = $message->toString();
        self::assertStringContainsString($paymentLinkUrl, $emailContent);
    }

    #[DataProvider('provideCaptureModesForGeneration')]
    public function testPaymentLinkRegeneration(string $captureMode): void
    {
        $this->setCaptureMode($captureMode);

        $this->testOrder->setState(OrderInterface::STATE_NEW);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_PROCESSING);

        $oldPaymentLinkId = 'PL_OLD_LINK_123456';
        $oldPaymentLinkUrl = 'https://test.adyen.link/PL_OLD_LINK_123456';
        $oldPaymentLink = new PaymentLink($payment, $oldPaymentLinkId, $oldPaymentLinkUrl);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($oldPaymentLink);
        $entityManager->flush();

        $existingPaymentLinks = $this->paymentLinkRepository->findBy(['payment' => $payment]);
        self::assertCount(1, $existingPaymentLinks);
        self::assertEquals($oldPaymentLinkId, $existingPaymentLinks[0]->getPaymentLinkId());

        $this->adyenClientStub->clearExpiredPaymentLinkIds();
        $this->adyenClientStub->setPaymentLinkResponse([
            'id' => 'PL_NEW_LINK_789012',
            'url' => 'https://test.adyen.link/PL_NEW_LINK_789012',
            'expiresAt' => '2024-12-31T23:59:59Z',
            'reference' => $this->testOrder->getNumber(),
            'amount' => [
                'value' => $payment->getAmount(),
                'currency' => $payment->getCurrencyCode(),
            ],
            'merchantAccount' => 'test_merchant',
            'status' => 'active',
        ]);

        $request = $this->createRequest();
        $response = ($this->generatePayLinkAction)((string) $payment->getId(), $request);

        self::assertInstanceOf(RedirectResponse::class, $response);

        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());

        $remainingPaymentLinks = $this->paymentLinkRepository->findBy(['payment' => $payment]);
        self::assertCount(1, $remainingPaymentLinks, 'Only one payment link should exist after regeneration');

        /** @var PaymentLinkInterface $newPaymentLink */
        $newPaymentLink = $remainingPaymentLinks[0];
        self::assertEquals('PL_NEW_LINK_789012', $newPaymentLink->getPaymentLinkId());
        self::assertEquals('https://test.adyen.link/PL_NEW_LINK_789012', $newPaymentLink->getPaymentLinkUrl());
        self::assertEquals($payment, $newPaymentLink->getPayment());

        $oldPaymentLinkCheck = $this->paymentLinkRepository->findOneBy(['paymentLinkId' => $oldPaymentLinkId]);
        self::assertNull($oldPaymentLinkCheck, 'Old payment link should be removed from database');

        $expiredPaymentLinkIds = $this->adyenClientStub->getExpiredPaymentLinkIds();
        self::assertContains($oldPaymentLinkId, $expiredPaymentLinkIds, 'Old payment link should have been expired through Adyen API');

        $paymentDetails = $payment->getDetails();
        self::assertArrayHasKey('id', $paymentDetails);
        self::assertEquals('PL_NEW_LINK_789012', $paymentDetails['id']);
        self::assertArrayHasKey('url', $paymentDetails);
        self::assertEquals('https://test.adyen.link/PL_NEW_LINK_789012', $paymentDetails['url']);
        self::assertArrayHasKey('status', $paymentDetails);
        self::assertEquals('active', $paymentDetails['status']);

        /** @var Email $message */
        $message = self::getMailerMessage();
        self::assertNotNull($message, 'An email should be sent for regeneration');

        $expectedEmail = $this->testOrder->getCustomer()->getEmail();
        $recipients = $message->getTo();
        self::assertCount(1, $recipients);
        self::assertEquals($expectedEmail, $recipients[0]->getAddress());

        $emailContent = $message->toString();
        self::assertStringContainsString('https://test.adyen.link/PL_NEW_LINK_789012', $emailContent);
        self::assertStringNotContainsString('https://test.adyen.link/PL_OLD_LINK_123456', $emailContent);
    }

    public static function provideCaptureModesForGeneration(): iterable
    {
        yield 'automatic' => [PaymentCaptureMode::AUTOMATIC];
        yield 'manual' => [PaymentCaptureMode::MANUAL];
    }

    public function testSuccessfulAuthorizationThroughPaymentLinkInAutomaticCaptureMode(): void
    {
        $this->setCaptureMode(PaymentCaptureMode::AUTOMATIC);

        $this->testOrder->setState(OrderInterface::STATE_NEW);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_PROCESSING);

        $paymentLinkId = 'PL_TEST_LINK_ID_123456';
        $paymentLink = new PaymentLink($payment, $paymentLinkId, 'dummy_link');

        $entityManager = $this->getEntityManager();
        $entityManager->persist($paymentLink);
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_AWAITING_PAYMENT, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());

        $existingPaymentLink = $this->paymentLinkRepository->findOneBy(['paymentLinkId' => $paymentLinkId]);
        self::assertNotNull($existingPaymentLink);
        self::assertInstanceOf(PaymentLinkInterface::class, $existingPaymentLink);
        self::assertEquals($paymentLinkId, $existingPaymentLink->getPaymentLinkId());
        self::assertEquals($payment, $existingPaymentLink->getPayment());

        $adyenReferences = $this->adyenReferenceRepository->findBy(['payment' => $payment]);
        self::assertCount(0, $adyenReferences);

        $webhookData = $this->createWebhookData(
            'AUTHORISATION',
            'AUTH_PSP_REF_789',
            $this->testOrder->getNumber(),
            [],
            true,
            $paymentLinkId,
        );

        $request = $this->createWebhookRequest($webhookData);
        $response = ($this->processNotificationsAction)(self::PAYMENT_METHOD_CODE, $request);

        self::assertEquals('[accepted]', $response->getContent());

        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());

        $deletedPaymentLink = $this->paymentLinkRepository->findOneBy(['paymentLinkId' => $paymentLinkId]);
        self::assertNull($deletedPaymentLink, 'Payment link should be removed after successful authorization');

        $adyenReferences = $this->adyenReferenceRepository->findBy(['payment' => $payment]);
        self::assertCount(1, $adyenReferences, 'A new AdyenReference should be created for the payment');

        /** @var AdyenReferenceInterface $adyenReference */
        $adyenReference = $adyenReferences[0];
        self::assertEquals('AUTH_PSP_REF_789', $adyenReference->getPspReference());
        self::assertEquals($payment, $adyenReference->getPayment());

        $paymentDetails = $payment->getDetails();
        self::assertArrayHasKey('pspReference', $paymentDetails);
        self::assertEquals('AUTH_PSP_REF_789', $paymentDetails['pspReference']);
        self::assertArrayHasKey('paymentLinkId', $paymentDetails['additionalData']);
    }

    public function testSuccessfulAuthorizationThroughPaymentLinkInManualCaptureModeAndCompleteAction(): void
    {
        $this->setCaptureMode(PaymentCaptureMode::MANUAL);

        $this->testOrder->setState(OrderInterface::STATE_NEW);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_PROCESSING);

        $paymentLinkId = 'PL_TEST_LINK_ID_123456';
        $paymentLink = new PaymentLink($payment, $paymentLinkId, 'dummy_link');

        $entityManager = $this->getEntityManager();
        $entityManager->persist($paymentLink);
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_AWAITING_PAYMENT, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());

        $existingPaymentLink = $this->paymentLinkRepository->findOneBy(['paymentLinkId' => $paymentLinkId]);
        self::assertNotNull($existingPaymentLink);
        self::assertInstanceOf(PaymentLinkInterface::class, $existingPaymentLink);
        self::assertEquals($paymentLinkId, $existingPaymentLink->getPaymentLinkId());
        self::assertEquals($payment, $existingPaymentLink->getPayment());

        $adyenReferences = $this->adyenReferenceRepository->findBy(['payment' => $payment]);
        self::assertCount(0, $adyenReferences);

        $webhookData = $this->createWebhookData(
            'AUTHORISATION',
            'AUTH_PSP_REF_789',
            $this->testOrder->getNumber(),
            [],
            true,
            $paymentLinkId,
        );

        $request = $this->createWebhookRequest($webhookData);
        $response = ($this->processNotificationsAction)(self::PAYMENT_METHOD_CODE, $request);

        self::assertEquals('[accepted]', $response->getContent());

        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_AUTHORIZED, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_AUTHORIZED, $payment->getState());

        $deletedPaymentLink = $this->paymentLinkRepository->findOneBy(['paymentLinkId' => $paymentLinkId]);
        self::assertNull($deletedPaymentLink, 'Payment link should be removed after successful authorization');

        $adyenReferences = $this->adyenReferenceRepository->findBy(['payment' => $payment]);
        self::assertCount(1, $adyenReferences, 'A new AdyenReference should be created for the payment');

        /** @var AdyenReferenceInterface $adyenReference */
        $adyenReference = $adyenReferences[0];
        self::assertEquals('AUTH_PSP_REF_789', $adyenReference->getPspReference());
        self::assertEquals($payment, $adyenReference->getPayment());

        $paymentDetails = $payment->getDetails();
        self::assertArrayHasKey('pspReference', $paymentDetails);
        self::assertEquals('AUTH_PSP_REF_789', $paymentDetails['pspReference']);
        self::assertArrayHasKey('paymentLinkId', $paymentDetails['additionalData']);

        $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_COMPLETE);
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }
}
