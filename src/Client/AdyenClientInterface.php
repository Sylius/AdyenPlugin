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

namespace Sylius\AdyenPlugin\Client;

use Adyen\Model\Checkout\PaypalUpdateOrderResponse;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Model\PaymentOutcome;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;

interface AdyenClientInterface
{
    public const TEST_ENVIRONMENT = 'test';

    public const LIVE_ENVIRONMENT = 'live';

    public const DEFAULT_OPTIONS = [
        'apiKey' => null,
        'merchantAccount' => null,
        'hmacKey' => null,
        'environment' => 'test',
        'authUser' => null,
        'authPassword' => null,
        'clientKey' => null,
    ];

    public const CREDIT_CARD_TYPE = 'scheme';

    public function getAvailablePaymentMethods(
        OrderInterface $order,
        ?AdyenTokenInterface $adyenToken = null,
    ): array;

    public function getEnvironment(): string;

    public function submitPayment(
        string $redirectUrl,
        array $receivedPayload,
        OrderInterface $order,
        ?AdyenTokenInterface $customerIdentifier = null,
    ): array;

    public function paymentDetails(
        array $receivedPayload,
        ?AdyenTokenInterface $adyenToken = null,
    ): array;

    public function requestRefund(
        PaymentInterface $payment,
        RefundPaymentGenerated $refund,
    ): array;

    public function requestReversal(PaymentInterface $payment): array;

    public function generatePaymentLink(PaymentInterface $payment): array;

    public function expirePaymentLink(string $paymentLinkId): array;

    public function removeStoredToken(
        string $paymentReference,
        AdyenTokenInterface $adyenToken,
    ): array;

    public function requestCancellation(PaymentInterface $payment): array;

    public function requestCapture(PaymentInterface $payment): array;

    public function submitPaypalPayments(array $receivedPayload, OrderInterface $order, string $returnUrl = ''): array;

    public function updatesOrderForPaypalExpressCheckout(string $pspReference, string $paymentData, OrderInterface $order): PaypalUpdateOrderResponse;
}
