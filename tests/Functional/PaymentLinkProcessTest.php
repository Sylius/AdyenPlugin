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

use Sylius\AdyenPlugin\Controller\Admin\GeneratePayLinkAction;
use Sylius\AdyenPlugin\Controller\Shop\ProcessNotificationsAction;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Entity\PaymentLink;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class PaymentLinkProcessTest extends AbstractAdyenFunctionalTestCase
{
    private ProcessNotificationsAction $processNotificationsAction;

    private GeneratePayLinkAction $generatePayLinkAction;

    protected function initializeServices($container): void
    {
        $this->processNotificationsAction = $this->getProcessNotificationsAction();
        $this->generatePayLinkAction = $this->getGeneratePayLinkAction();
    }

    public function testPaymentLinkGeneration(): void
    {
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
            'id' => 'PL_TEST_GENERATED_123456',
            'url' => 'https://test.adyen.link/PL_TEST_GENERATED_123456',
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
        self::assertEquals('PL_TEST_GENERATED_123456', $paymentLink->getPaymentLinkId());
        self::assertEquals('https://test.adyen.link/PL_TEST_GENERATED_123456', $paymentLink->getPaymentLinkUrl());
        self::assertEquals($payment, $paymentLink->getPayment());

        $paymentDetails = $payment->getDetails();
        self::assertArrayHasKey('id', $paymentDetails);
        self::assertEquals('PL_TEST_GENERATED_123456', $paymentDetails['id']);
        self::assertArrayHasKey('url', $paymentDetails);
        self::assertEquals('https://test.adyen.link/PL_TEST_GENERATED_123456', $paymentDetails['url']);
        self::assertArrayHasKey('status', $paymentDetails);
        self::assertEquals('active', $paymentDetails['status']);
    }

    public function testSuccessfulAuthorizationThroughPaymentLink(): void
    {
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
}
