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

use Sylius\AdyenPlugin\Controller\Shop\PaymentDetailsAction;
use Sylius\AdyenPlugin\Controller\Shop\PaymentsAction;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Tests\Sylius\AdyenPlugin\Functional\AdyenTestCase;

final class CheckoutWith3dsTest extends AdyenTestCase
{
    private PaymentsAction $paymentsAction;

    private PaymentDetailsAction $paymentsDetailsAction;

    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();
        $this->setCaptureMode(PaymentCaptureMode::AUTOMATIC);

        $this->paymentsAction = $this->getPaymentsAction();
        $this->paymentsDetailsAction = $this->getPaymentDetailsAction();

        $container->set('sylius.email_sender', $this->createMock(SenderInterface::class));
    }

    public function testCheckoutWith3DSRedirect(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'RedirectShopper',
            'action' => [
                'type' => 'redirect',
                'url' => 'https://test.adyen.com/3ds-redirect',
                'method' => 'POST',
                'data' => [
                    'MD' => 'test_md',
                    'PaReq' => 'test_pareq',
                ],
            ],
            'details' => [
                ['key' => 'MD', 'type' => 'text'],
                ['key' => 'PaRes', 'type' => 'text'],
            ],
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

        $request->getSession()->set('sylius_order_id', 123);

        $response = ($this->paymentsAction)($request);

        self::assertEquals(200, $response->getStatusCode());

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('resultCode', $responseData);
        self::assertEquals('RedirectShopper', $responseData['resultCode']);
        self::assertArrayHasKey('action', $responseData);
        self::assertEquals('redirect', $responseData['action']['type']);
        self::assertArrayHasKey('url', $responseData['action']);

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());
        self::assertArrayHasKey('resultCode', $payment->getDetails());
        self::assertEquals('RedirectShopper', $payment->getDetails()['resultCode']);
        self::assertArrayHasKey('action', $payment->getDetails());
        self::assertEquals('redirect', $payment->getDetails()['action']['type']);
    }

    public function testCheckoutWith3DSChallengeShopper(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'ChallengeShopper',
            'action' => [
                'type' => 'threeDS2Challenge',
                'token' => 'test_challenge_token',
            ],
            'details' => [
                ['key' => 'threeds2.challengeResult', 'type' => 'text'],
            ],
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

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('resultCode', $responseData);
        self::assertEquals('ChallengeShopper', $responseData['resultCode']);
        self::assertArrayHasKey('action', $responseData);
        self::assertEquals('threeDS2Challenge', $responseData['action']['type']);

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());
        self::assertArrayHasKey('resultCode', $payment->getDetails());
        self::assertEquals('ChallengeShopper', $payment->getDetails()['resultCode']);
        self::assertArrayHasKey('action', $payment->getDetails());
        self::assertEquals('threeDS2Challenge', $payment->getDetails()['action']['type']);
    }

    public function test3DSRedirectThenWebhookCompletesPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'RedirectShopper',
            'pspReference' => 'TEST_PSP_REF_123',
            'merchantReference' => 'ORDER_123',
            'action' => [
                'type' => 'redirect',
                'url' => 'https://test.adyen.com/3ds-redirect',
                'method' => 'POST',
                'data' => [
                    'MD' => 'test_md',
                    'PaReq' => 'test_pareq',
                ],
            ],
            'details' => [
                ['key' => 'MD', 'type' => 'text'],
                ['key' => 'PaRes', 'type' => 'text'],
            ],
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
        self::assertNotNull($payment);
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());
        self::assertEquals('RedirectShopper', $payment->getDetails()['resultCode']);

        $this->simulateWebhook($payment, EventCodeResolverInterface::EVENT_AUTHORIZATION, true);

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function test3DSReturnWithPaymentDetailsCompletesPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'RedirectShopper',
            'action' => [
                'type' => 'redirect',
                'url' => 'https://test.adyen.com/3ds-redirect',
                'method' => 'POST',
                'data' => [
                    'MD' => 'test_md',
                    'PaReq' => 'test_pareq',
                ],
            ],
            'details' => [
                ['key' => 'MD', 'type' => 'text'],
                ['key' => 'PaRes', 'type' => 'text'],
            ],
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

        ($this->paymentsAction)($request);

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());
        self::assertEquals('RedirectShopper', $payment->getDetails()['resultCode']);

        $this->adyenClientStub->setPaymentDetailsResponse([
            'resultCode' => 'Authorised',
        ]);

        $detailsRequest = $this->createRequest([
            'details' => [
                'MD' => 'test_md',
                'PaRes' => 'test_pares',
            ],
        ]);

        $response = ($this->paymentsDetailsAction)($detailsRequest);
        self::assertEquals(200, $response->getStatusCode());

        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function test3DSReturnWithPaymentDetailsRefused(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'RedirectShopper',
            'action' => [
                'type' => 'redirect',
                'url' => 'https://test.adyen.com/3ds-redirect',
                'method' => 'POST',
                'data' => [
                    'MD' => 'test_md',
                    'PaReq' => 'test_pareq',
                ],
            ],
            'details' => [
                ['key' => 'MD', 'type' => 'text'],
                ['key' => 'PaRes', 'type' => 'text'],
            ],
        ]);

        ($this->paymentsAction)($this->createRequest([
            'paymentMethod' => [
                'type' => 'scheme',
            ],
        ]));

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals('RedirectShopper', $payment->getDetails()['resultCode']);

        $this->adyenClientStub->setPaymentDetailsResponse([
            'resultCode' => 'Refused',
            'refusalReason' => 'Authentication failed',
        ]);

        $detailsRequest = $this->createRequest([
            'details' => [
                'MD' => 'test_md',
                'PaRes' => 'test_pares',
            ],
        ]);

        $response = ($this->paymentsDetailsAction)($detailsRequest);

        self::assertEquals(200, $response->getStatusCode());

        self::assertEquals('Refused', $payment->getDetails()['resultCode']);
        self::assertEquals('Authentication failed', $payment->getDetails()['refusalReason']);
        self::assertEquals(PaymentInterface::STATE_FAILED, $payment->getState());
    }

    public function test3DSChallengeThenDetailsAuthorisedCompletesPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'ChallengeShopper',
            'action' => [
                'type' => 'threeDS2Challenge',
                'token' => 'test_challenge_token',
            ],
            'details' => [
                ['key' => 'threeds2.challengeResult', 'type' => 'text'],
            ],
        ]);

        ($this->paymentsAction)($this->createRequest([
            'paymentMethod' => [
                'type' => 'scheme',
            ],
        ]));

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals('ChallengeShopper', $payment->getDetails()['resultCode']);

        $this->adyenClientStub->setPaymentDetailsResponse([
            'resultCode' => 'Authorised',
        ]);

        $detailsRequest = $this->createRequest([
            'details' => [
                'threeDSResult' => '{"transStatus":"Y"}',
            ],
        ]);

        $response = ($this->paymentsDetailsAction)($detailsRequest);

        self::assertEquals(200, $response->getStatusCode());

        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function test3DSChallengeThenDetailsRefusedFailsPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'ChallengeShopper',
            'action' => [
                'type' => 'threeDS2Challenge',
                'token' => 'test_challenge_token',
            ],
            'details' => [
                ['key' => 'threeds2.challengeResult', 'type' => 'text'],
            ],
        ]);

        ($this->paymentsAction)($this->createRequest([
            'paymentMethod' => [
                'type' => 'scheme',
            ],
        ]));

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals('ChallengeShopper', $payment->getDetails()['resultCode']);

        $this->adyenClientStub->setPaymentDetailsResponse([
            'resultCode' => 'Refused',
            'refusalReason' => 'Authentication failed',
        ]);

        $detailsRequest = $this->createRequest([
            'details' => [
                'threeDSResult' => '{"transStatus":"N"}',
            ],
        ]);

        $response = ($this->paymentsDetailsAction)($detailsRequest);

        self::assertEquals(200, $response->getStatusCode());

        self::assertEquals('Refused', $payment->getDetails()['resultCode']);
        self::assertEquals('Authentication failed', $payment->getDetails()['refusalReason']);
        self::assertEquals(PaymentInterface::STATE_FAILED, $payment->getState());
    }

    public function test3DSIdentifyThenDetailsAuthorisedCompletesPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'IdentifyShopper',
            'action' => [
                'type' => 'threeDS2',
                'subtype' => 'fingerprint',
                'token' => 'test_fingerprint_token',
                'paymentMethodType' => 'scheme',
                'authorisationToken' => 'test_authorisation_token',
                'paymentData' => 'test_payment_data',
            ],
        ]);

        ($this->paymentsAction)($this->createRequest([
            'paymentMethod' => [
                'type' => 'scheme',
            ],
        ]));

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals('IdentifyShopper', $payment->getDetails()['resultCode']);
        self::assertEquals('threeDS2', $payment->getDetails()['action']['type']);
        self::assertEquals('fingerprint', $payment->getDetails()['action']['subtype'] ?? null);

        $this->adyenClientStub->setPaymentDetailsResponse([
            'resultCode' => 'Authorised',
        ]);

        $detailsRequest = $this->createRequest([
            'details' => [
                'threeDSResult' => '{"fingerprint":"ok"}',
            ],
        ]);

        $response = ($this->paymentsDetailsAction)($detailsRequest);
        self::assertEquals(200, $response->getStatusCode());

        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
    }

    public function test3DSIdentifyThenDetailsRefusedFailsPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'IdentifyShopper',
            'action' => [
                'type' => 'threeDS2',
                'subtype' => 'fingerprint',
                'token' => 'test_fingerprint_token',
                'paymentMethodType' => 'scheme',
                'authorisationToken' => 'test_authorisation_token',
                'paymentData' => 'test_payment_data',
            ],
        ]);

        ($this->paymentsAction)($this->createRequest([
            'paymentMethod' => [
                'type' => 'scheme',
            ],
        ]));

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals('IdentifyShopper', $payment->getDetails()['resultCode']);

        $this->adyenClientStub->setPaymentDetailsResponse([
            'resultCode' => 'Refused',
            'refusalReason' => 'Authentication failed',
        ]);

        $detailsRequest = $this->createRequest([
            'details' => [
                'threeDSResult' => '{"fingerprint":"bad"}',
            ],
        ]);

        $response = ($this->paymentsDetailsAction)($detailsRequest);
        self::assertEquals(200, $response->getStatusCode());

        self::assertEquals('Refused', $payment->getDetails()['resultCode']);
        self::assertEquals('Authentication failed', $payment->getDetails()['refusalReason']);
        self::assertEquals(PaymentInterface::STATE_FAILED, $payment->getState());
    }
}
