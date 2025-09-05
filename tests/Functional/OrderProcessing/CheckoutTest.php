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

use Adyen\AdyenException;
use Sylius\AdyenPlugin\Controller\Shop\PaymentsAction;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Tests\Sylius\AdyenPlugin\Functional\AdyenTestCase;

final class CheckoutTest extends AdyenTestCase
{
    private PaymentsAction $paymentsAction;

    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();
        $this->setCaptureMode(PaymentCaptureMode::AUTOMATIC);

        $this->paymentsAction = $this->getPaymentsAction();

        $container->set('sylius.email_sender', $this->createMock(SenderInterface::class));
    }

    public function testSuccessfulCheckoutWithCardPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Authorised',
            'pspReference' => 'TEST_PSP_REF_123',
            'merchantReference' => 'ORDER_123',
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
        self::assertEquals('Authorised', $responseData['resultCode']);
        self::assertArrayHasKey('pspReference', $responseData);
        self::assertEquals('TEST_PSP_REF_123', $responseData['pspReference']);
        self::assertArrayHasKey('redirect', $responseData);

        $payment = $this->testOrder->getLastPayment();

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertArrayHasKey('resultCode', $payment->getDetails());
        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);
        self::assertArrayHasKey('pspReference', $payment->getDetails());
        self::assertEquals('TEST_PSP_REF_123', $payment->getDetails()['pspReference']);
    }

    public function testSuccessfulCheckoutWithCardPaymentUsingManualCaptureMode(): void
    {
        $this->setCaptureMode(PaymentCaptureMode::MANUAL);

        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Authorised',
            'pspReference' => 'TEST_PSP_REF_123',
            'merchantReference' => 'ORDER_123',
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
        self::assertEquals('Authorised', $responseData['resultCode']);
        self::assertArrayHasKey('pspReference', $responseData);
        self::assertEquals('TEST_PSP_REF_123', $responseData['pspReference']);
        self::assertArrayHasKey('redirect', $responseData);

        $payment = $this->testOrder->getLastPayment();

        self::assertEquals(PaymentInterface::STATE_AUTHORIZED, $payment->getState());
        self::assertArrayHasKey('resultCode', $payment->getDetails());
        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);
        self::assertArrayHasKey('pspReference', $payment->getDetails());
        self::assertEquals('TEST_PSP_REF_123', $payment->getDetails()['pspReference']);
    }

    public function testCheckoutWithInvalidPaymentMethod(): void
    {
        $this->adyenClientStub->setThrowException(new AdyenException('Invalid payment method', 422));

        $request = $this->createRequest([
            'paymentMethod' => ['type' => 'invalid'],
        ]);

        $request->getSession()->set('sylius_order_id', 123);

        $response = ($this->paymentsAction)($request);

        self::assertEquals(422, $response->getStatusCode());

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('error', $responseData);
        self::assertTrue($responseData['error']);
        self::assertArrayHasKey('message', $responseData);
        self::assertEquals('Invalid payment method', $responseData['message']);

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());
        self::assertEmpty($payment->getDetails());
    }

    public function testCheckoutWithStoredPaymentMethod(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Authorised',
            'pspReference' => 'STORED_PSP_REF',
        ]);

        $request = $this->createRequest([
            'paymentMethod' => [
                'storedPaymentMethodId' => 'stored_token_123',
            ],
        ]);

        $response = ($this->paymentsAction)($request, self::PAYMENT_METHOD_CODE);

        self::assertEquals(200, $response->getStatusCode());

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertEquals('Authorised', $responseData['resultCode']);
        self::assertEquals('STORED_PSP_REF', $responseData['pspReference']);

        $payment = $this->testOrder->getLastPayment();
        self::assertNotNull($payment);

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertArrayHasKey('resultCode', $payment->getDetails());
        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);
        self::assertEquals('STORED_PSP_REF', $payment->getDetails()['pspReference']);
    }

    public function testPaymentStateTransitionsOnSuccessfulCheckout(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Authorised',
            'pspReference' => 'STATE_TEST_PSP_REF',
            'merchantReference' => 'ORDER_123',
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

        $payment = $this->testOrder->getLastPayment();
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());
        self::assertEmpty($payment->getDetails());

        $response = ($this->paymentsAction)($request);

        self::assertEquals(200, $response->getStatusCode());
        $responseData = json_decode((string) $response->getContent(), true);
        self::assertEquals('Authorised', $responseData['resultCode']);

        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertNotEmpty($payment->getDetails());
        self::assertEquals('Authorised', $payment->getDetails()['resultCode']);
        self::assertEquals('STATE_TEST_PSP_REF', $payment->getDetails()['pspReference']);

        self::assertNotNull($payment->getOrder());
        self::assertEquals($this->testOrder->getId(), $payment->getOrder()->getId());
    }

    public function testPaymentStateOnPendingPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Pending',
            'pspReference' => 'PENDING_PSP_REF',
            'additionalData' => [
                'paymentMethod' => 'ideal',
            ],
        ]);

        $request = $this->createRequest([
            'paymentMethod' => [
                'type' => 'ideal',
                'issuer' => 'test_issuer',
            ],
        ]);

        $response = ($this->paymentsAction)($request);

        self::assertEquals(200, $response->getStatusCode());
        $responseData = json_decode((string) $response->getContent(), true);
        self::assertEquals('Pending', $responseData['resultCode']);

        $payment = $this->testOrder->getLastPayment();
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());
        self::assertEquals('Pending', $payment->getDetails()['resultCode']);
        self::assertEquals('PENDING_PSP_REF', $payment->getDetails()['pspReference']);
    }

    public function testPaymentStateOnRefusedPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Refused',
            'pspReference' => 'REFUSED_PSP_REF',
            'refusalReason' => 'Insufficient funds',
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
        self::assertEquals('Refused', $responseData['resultCode']);

        $failedPayment = $this->testOrder->getLastPayment(PaymentInterface::STATE_FAILED);
        self::assertNotNull($failedPayment);
        self::assertEquals(PaymentInterface::STATE_FAILED, $failedPayment->getState());
        self::assertEquals('Refused', $failedPayment->getDetails()['resultCode']);
        self::assertEquals('REFUSED_PSP_REF', $failedPayment->getDetails()['pspReference']);
        self::assertArrayHasKey('refusalReason', $failedPayment->getDetails());
        self::assertEquals('Insufficient funds', $failedPayment->getDetails()['refusalReason']);
    }

    public function testOrderPaymentStateAndAmount(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Authorised',
            'pspReference' => 'AMOUNT_TEST_PSP_REF',
        ]);

        $request = $this->createRequest([
            'paymentMethod' => ['type' => 'scheme'],
        ]);

        $response = ($this->paymentsAction)($request);

        self::assertEquals(200, $response->getStatusCode());

        $order = $this->testOrder;
        self::assertNotNull($order);
        self::assertEquals('USD', $order->getCurrencyCode());
        self::assertEquals('en_US', $order->getLocaleCode());

        $payment = $order->getLastPayment();
        self::assertNotNull($payment);
        self::assertEquals($order->getCurrencyCode(), $payment->getCurrencyCode());
        self::assertEquals(10000, $payment->getAmount());

        self::assertNotNull($payment->getMethod());
        self::assertEquals(self::PAYMENT_METHOD_CODE, $payment->getMethod()->getCode());
        self::assertEquals(self::PAYMENT_METHOD_NAME, $payment->getMethod()->getName());
    }

    public function testPaymentDetailsArePersisted(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Authorised',
            'pspReference' => 'DETAILS_PERSIST_REF',
            'merchantReference' => 'ORDER_123',
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
                'lastFour' => '1234',
            ],
            'additionalData' => [
                'cardSummary' => '1234',
                'expiryDate' => '12/2025',
                'authorisationCode' => 'ABC123',
            ],
            'amount' => [
                'currency' => 'USD',
                'value' => 10000,
            ],
        ]);

        $request = $this->createRequest([
            'paymentMethod' => [
                'type' => 'scheme',
                'encryptedCardNumber' => 'test_encrypted_number',
                'encryptedExpiryMonth' => '12',
                'encryptedExpiryYear' => '2025',
                'encryptedSecurityCode' => 'test_encrypted_cvv',
            ],
        ]);

        $response = ($this->paymentsAction)($request);

        self::assertEquals(200, $response->getStatusCode());

        $payment = $this->testOrder->getLastPayment();
        $details = $payment->getDetails();

        self::assertArrayHasKey('resultCode', $details);
        self::assertEquals('Authorised', $details['resultCode']);

        self::assertArrayHasKey('pspReference', $details);
        self::assertEquals('DETAILS_PERSIST_REF', $details['pspReference']);

        self::assertArrayHasKey('merchantReference', $details);
        self::assertEquals('ORDER_123', $details['merchantReference']);

        self::assertArrayHasKey('paymentMethod', $details);
        self::assertArrayHasKey('type', $details['paymentMethod']);
        self::assertEquals('scheme', $details['paymentMethod']['type']);
        self::assertArrayHasKey('brand', $details['paymentMethod']);
        self::assertEquals('visa', $details['paymentMethod']['brand']);
        self::assertArrayHasKey('lastFour', $details['paymentMethod']);
        self::assertEquals('1234', $details['paymentMethod']['lastFour']);

        self::assertArrayHasKey('additionalData', $details);
        self::assertArrayHasKey('cardSummary', $details['additionalData']);
        self::assertEquals('1234', $details['additionalData']['cardSummary']);
        self::assertArrayHasKey('authorisationCode', $details['additionalData']);
        self::assertEquals('ABC123', $details['additionalData']['authorisationCode']);

        self::assertArrayHasKey('amount', $details);
        self::assertArrayHasKey('currency', $details['amount']);
        self::assertEquals('USD', $details['amount']['currency']);
        self::assertArrayHasKey('value', $details['amount']);
        self::assertEquals(10000, $details['amount']['value']);
    }
}
