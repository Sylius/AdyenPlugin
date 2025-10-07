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

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Model\Checkout\PaymentCancelRequest;
use Adyen\Model\Checkout\PaymentCaptureRequest;
use Adyen\Model\Checkout\PaymentDetailsRequest;
use Adyen\Model\Checkout\PaymentLinkRequest;
use Adyen\Model\Checkout\PaymentMethodsRequest;
use Adyen\Model\Checkout\PaymentMethodsResponse;
use Adyen\Model\Checkout\PaymentRefundRequest;
use Adyen\Model\Checkout\PaymentRequest;
use Adyen\Model\Checkout\PaymentReversalRequest;
use Adyen\Model\Checkout\PaypalUpdateOrderRequest;
use Adyen\Model\Checkout\UpdatePaymentLinkRequest;
use Adyen\Service\Checkout\ModificationsApi;
use Adyen\Service\Checkout\PaymentLinksApi;
use Adyen\Service\Checkout\PaymentsApi;
use Adyen\Service\Checkout\RecurringApi;
use Adyen\Service\Checkout\UtilityApi;
use Payum\Core\Bridge\Spl\ArrayObject;
use Sylius\AdyenPlugin\Entity\ShopperReferenceInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;

final class AdyenClient implements AdyenClientInterface
{
    private readonly ArrayObject $options;

    private readonly Client $transport;

    public function __construct(
        array $options,
        AdyenTransportFactoryInterface $adyenTransportFactory,
        private readonly ClientPayloadFactoryInterface $clientPayloadFactory,
    ) {
        $options = ArrayObject::ensureArrayObject($options);
        $options->defaults(self::DEFAULT_OPTIONS);
        $options->validateNotEmpty([
            'apiKey',
            'merchantAccount',
            'hmacKey',
            'authUser',
            'authPassword',
            'clientKey',
        ]);

        $this->options = $options;
        $this->transport = $adyenTransportFactory->create($options->getArrayCopy());
    }

    public function getPaymentMethodsResponse(
        OrderInterface $order,
        ?ShopperReferenceInterface $shopperReference = null,
        bool $manualCapture = false,
    ): PaymentMethodsResponse {
        $payload = $this->clientPayloadFactory->createForAvailablePaymentMethods(
            $this->options,
            $order,
            $shopperReference,
            $manualCapture,
        );

        return $this->getPaymentsApi()->paymentMethods(new PaymentMethodsRequest($payload));
    }

    public function paymentDetails(
        array $receivedPayload,
        ?ShopperReferenceInterface $shopperReference = null,
    ): array {
        $payload = $this->clientPayloadFactory->createForPaymentDetails(
            $receivedPayload,
            $shopperReference,
        );

        $response = $this->getPaymentsApi()->paymentsDetails(new PaymentDetailsRequest($payload));

        return $response->toArray();
    }

    public function submitPayment(
        string $redirectUrl,
        array $receivedPayload,
        OrderInterface $order,
        bool $manualCapture = false,
        ?ShopperReferenceInterface $customerIdentifier = null,
    ): array {
        if (!isset($receivedPayload['paymentMethod'])) {
            throw new \InvalidArgumentException();
        }

        $payload = $this->clientPayloadFactory->createForSubmitPayment(
            $this->options,
            $redirectUrl,
            $receivedPayload,
            $order,
            $manualCapture,
            $customerIdentifier,
        );

        $response = $this->getPaymentsApi()->payments(new PaymentRequest($payload));

        return $response->toArray();
    }

    public function requestCapture(
        PaymentInterface $payment,
    ): array {
        $payload = $this->clientPayloadFactory->createForCapture($this->options, $payment);

        $response = $this->getModificationsApi()->captureAuthorisedPayment(
            $payment->getDetails()['pspReference'],
            new PaymentCaptureRequest($payload),
        );

        return $response->toArray();
    }

    public function requestCancellation(
        PaymentInterface $payment,
    ): array {
        $payload = $this->clientPayloadFactory->createForCancel($this->options, $payment);

        $response = $this->getModificationsApi()->cancelAuthorisedPaymentByPspReference(
            $payment->getDetails()['pspReference'],
            new PaymentCancelRequest($payload),
        );

        return $response->toArray();
    }

    public function removeStoredToken(
        string $storedPaymentMethodReference,
        ShopperReferenceInterface $shopperReference,
    ): void {
        $this->getRecurringApi()->deleteTokenForStoredPaymentDetails(
            $storedPaymentMethodReference,
            $this->clientPayloadFactory->createForTokenRemove(
                $this->options,
                $storedPaymentMethodReference,
                $shopperReference,
            ),
        );
    }

    public function requestRefund(
        PaymentInterface $payment,
        RefundPaymentGenerated $refund,
    ): array {
        $payload = $this->clientPayloadFactory->createForRefund($this->options, $payment, $refund);

        $response = $this->getModificationsApi()->refundCapturedPayment(
            $payment->getDetails()['pspReference'],
            new PaymentRefundRequest($payload),
        );

        return $response->toArray();
    }

    public function requestReversal(PaymentInterface $payment): array
    {
        $payload = $this->clientPayloadFactory->createForReversal($this->options, $payment);

        $response = $this->getModificationsApi()->refundOrCancelPayment(
            $payment->getDetails()['pspReference'],
            new PaymentReversalRequest($payload),
        );

        return $response->toArray();
    }

    public function generatePaymentLink(PaymentInterface $payment): array
    {
        $payload = $this->clientPayloadFactory->createForPaymentLink($this->options, $payment);

        $response = $this->getPaymentLinksApi()->paymentLinks(new PaymentLinkRequest($payload));

        return $response->toArray();
    }

    public function expirePaymentLink(string $paymentLinkId): array
    {
        $payload = $this->clientPayloadFactory->createForPaymentLinkExpiration($this->options, $paymentLinkId);

        $response = $this->getPaymentLinksApi()->updatePaymentLink(
            $paymentLinkId,
            new UpdatePaymentLinkRequest($payload),
        );

        return $response->toArray();
    }

    public function submitPaypalPayments(array $receivedPayload, OrderInterface $order, string $returnUrl = ''): array
    {
        $payload = $this->clientPayloadFactory->createForPaypalPayments(
            $this->options,
            $receivedPayload,
            $order,
            $returnUrl,
        );

        $response = $this->getPaymentsApi()->payments(new PaymentRequest($payload));

        return $response->toArray();
    }

    public function updatesOrderForPaypalExpressCheckout(
        string $pspReference,
        string $paymentData,
        OrderInterface $order,
    ): array {
        $payload = $this->clientPayloadFactory->createPaypalUpdateOrderRequest(
            $pspReference,
            $paymentData,
            $order,
        );

        $response = $this->getCheckoutUtilityApi()->updatesOrderForPaypalExpressCheckout(
            new PaypalUpdateOrderRequest($payload),
        );

        return $response->toArray();
    }

    public function getEnvironment(): string
    {
        return (string) $this->options['environment'];
    }

    /** @throws AdyenException */
    private function getPaymentsApi(): PaymentsApi
    {
        return new PaymentsApi($this->transport);
    }

    /** @throws AdyenException */
    private function getModificationsApi(): ModificationsApi
    {
        return new ModificationsApi($this->transport);
    }

    /** @throws AdyenException */
    private function getRecurringApi(): RecurringApi
    {
        return new RecurringApi($this->transport);
    }

    /** @throws AdyenException */
    private function getCheckoutUtilityApi(): UtilityApi
    {
        return new UtilityApi($this->transport);
    }

    /** @throws AdyenException */
    private function getPaymentLinksApi(): PaymentLinksApi
    {
        return new PaymentLinksApi($this->transport);
    }
}
