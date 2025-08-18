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

use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;

final class AdyenClientStub implements AdyenClientInterface
{
    private array $submitPaymentResponse = [];

    private ?\Exception $exception = null;

    public function setSubmitPaymentResponse(array $response): void
    {
        $this->submitPaymentResponse = $response;
        $this->exception = null;
    }

    public function setThrowException(\Exception $exception): void
    {
        $this->exception = $exception;
    }

    public function submitPayment(
        string $redirectUrl,
        array $receivedPayload,
        OrderInterface $order,
        ?AdyenTokenInterface $customerIdentifier = null,
    ): array {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->submitPaymentResponse;
    }

    public function getAvailablePaymentMethods(
        OrderInterface $order,
        ?AdyenTokenInterface $adyenToken = null,
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
        ?AdyenTokenInterface $adyenToken = null,
    ): array {
        return [
            'resultCode' => 'Authorised',
            'pspReference' => 'DETAILS_PSP_REF',
        ];
    }

    public function requestRefund(
        PaymentInterface $payment,
        RefundPaymentGenerated $refund,
    ): array {
        return [
            'status' => 'received',
            'pspReference' => 'REFUND_PSP_REF',
        ];
    }

    public function removeStoredToken(
        string $paymentReference,
        AdyenTokenInterface $adyenToken,
    ): array {
        return [
            'status' => 'success',
            'message' => 'Token removed',
        ];
    }

    public function requestCancellation(PaymentInterface $payment): array
    {
        return [
            'status' => 'received',
            'pspReference' => 'CANCEL_PSP_REF',
        ];
    }

    public function requestCapture(PaymentInterface $payment): array
    {
        return [
            'status' => 'received',
            'pspReference' => 'CAPTURE_PSP_REF',
        ];
    }
}
