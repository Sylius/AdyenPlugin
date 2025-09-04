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

namespace Tests\Sylius\AdyenPlugin\Functional\Stub;

use Adyen\Model\Checkout\PaypalUpdateOrderResponse;
use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Client\ResponseStatus;
use Sylius\AdyenPlugin\Entity\ShopperReferenceInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;

final class AdyenClientStub implements AdyenClientInterface
{
    private array $submitPaymentResponse = [];

    private array $paymentDetailsResponse = [];

    private array $reversalResponse = [];

    private ?array $lastReversalRequest = null;

    private array $paymentLinkResponse = [];

    private array $expiredPaymentLinkIds = [];

    private ?\Exception $exception = null;

    public function setSubmitPaymentResponse(array $response): void
    {
        $this->submitPaymentResponse = $response;
        $this->exception = null;
    }

    public function setPaymentDetailsResponse(array $response): void
    {
        $this->paymentDetailsResponse = $response;
        $this->exception = null;
    }

    public function setThrowException(\Exception $exception): void
    {
        $this->exception = $exception;
    }

    public function setReversalResponse(array $response): void
    {
        $this->reversalResponse = $response;
        $this->exception = null;
    }

    public function setPaymentLinkResponse(array $response): void
    {
        $this->paymentLinkResponse = $response;
        $this->exception = null;
    }

    public function getLastReversalRequest(): ?array
    {
        return $this->lastReversalRequest;
    }

    public function getExpiredPaymentLinkIds(): array
    {
        return $this->expiredPaymentLinkIds;
    }

    public function clearExpiredPaymentLinkIds(): void
    {
        $this->expiredPaymentLinkIds = [];
    }

    public function submitPayment(
        string $redirectUrl,
        array $receivedPayload,
        OrderInterface $order,
        ?ShopperReferenceInterface $customerIdentifier = null,
    ): array {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->submitPaymentResponse;
    }

    public function getAvailablePaymentMethods(
        OrderInterface $order,
        ?ShopperReferenceInterface $shopperReference = null,
    ): array {
        return [
            'paymentMethods' => [
                [
                    'type' => 'scheme',
                    'name' => 'Cards',
                ],
            ],
        ];
    }

    public function getEnvironment(): string
    {
        return self::TEST_ENVIRONMENT;
    }

    public function paymentDetails(
        array $receivedPayload,
        ?ShopperReferenceInterface $shopperReference = null,
    ): array {
        $base = [
            'pspReference' => 'DETAILS_PSP_REF',
            'merchantReference' => $receivedPayload['merchantReference'] ?? 'TEST_ORDER',
            'paymentMethod' => [
                'type' => $receivedPayload['paymentMethod']['type'] ?? 'scheme',
            ],
            'amount' => [
                'currency' => 'USD',
                'value' => 10000,
            ],
            'additionalData' => [
                'cvcResult' => '0',
            ],
        ];

        if (isset($receivedPayload['details']['threeDSResult'])) {
            $base['threeDS2Result'] = [
                'raw' => $receivedPayload['details']['threeDSResult'],
            ];
        }

        $configured = $this->paymentDetailsResponse ?: ['resultCode' => 'Authorised'];

        if (($configured['resultCode'] ?? null) === 'Refused' && !isset($configured['refusalReasonCode'])) {
            $configured['refusalReasonCode'] = '11'; // 3D Not Authenticated
        }

        return array_merge($base, $configured);
    }

    public function requestRefund(
        PaymentInterface $payment,
        RefundPaymentGenerated $refund,
    ): array {
        return [
            'status' => ResponseStatus::RECEIVED,
            'pspReference' => 'REFUND_PSP_REF',
        ];
    }

    public function requestReversal(PaymentInterface $payment): array
    {
        $details = $payment->getDetails();
        $this->lastReversalRequest = [
            'paymentPspReference' => $details['pspReference'] ?? null,
        ];

        if ([] !== $this->reversalResponse) {
            return $this->reversalResponse;
        }

        return [
            'status' => ResponseStatus::RECEIVED,
            'pspReference' => 'REVERSAL_PSP_REF',
        ];
    }

    public function removeStoredToken(
        string $paymentReference,
        ShopperReferenceInterface $shopperReference,
    ): array {
        return [];
    }

    public function requestCancellation(PaymentInterface $payment): array
    {
        return [
            'status' => ResponseStatus::RECEIVED,
            'pspReference' => 'CANCEL_PSP_REF',
        ];
    }

    public function requestCapture(PaymentInterface $payment): array
    {
        return [
            'status' => ResponseStatus::RECEIVED,
            'pspReference' => 'CAPTURE_PSP_REF',
        ];
    }

    public function generatePaymentLink(PaymentInterface $payment): array
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        if ([] !== $this->paymentLinkResponse) {
            return $this->paymentLinkResponse;
        }

        return [
            'id' => 'PAYMENT_LINK_ID',
            'url' => 'https://test.adyen.link/PL123456789',
            'expiresAt' => '2024-12-31T23:59:59Z',
            'reference' => $payment->getOrder()?->getNumber() ?? 'REF123',
            'amount' => [
                'value' => $payment->getAmount(),
                'currency' => $payment->getCurrencyCode(),
            ],
            'merchantAccount' => 'TestMerchant',
            'status' => 'active',
        ];
    }

    public function expirePaymentLink(string $paymentLinkId): array
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        $this->expiredPaymentLinkIds[] = $paymentLinkId;

        return [
            'id' => $paymentLinkId,
            'status' => 'expired',
        ];
    }

    public function submitPaypalPayments(array $receivedPayload, OrderInterface $order, string $returnUrl = ''): array
    {
        return [];
    }

    public function updatesOrderForPaypalExpressCheckout(string $pspReference, string $paymentData, OrderInterface $order): PaypalUpdateOrderResponse
    {
        return new PaypalUpdateOrderResponse([]);
    }
}
