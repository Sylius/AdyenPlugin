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
use Adyen\Model\Checkout\PaymentMethodsRequest;
use Adyen\Model\Checkout\PaymentMethodsResponse;
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
    ): PaymentMethodsResponse {
        return $this->getPaymentsApi()->paymentMethods(
            new PaymentMethodsRequest(
                $this->clientPayloadFactory->createForAvailablePaymentMethods($this->options, $order, $shopperReference),
            ),
        );
    }

    public function paymentDetails(
        array $receivedPayload,
        ?ShopperReferenceInterface $shopperReference = null,
    ): array {
        $response = $this->getPaymentsApi()->paymentsDetails(
            $this->clientPayloadFactory->createForPaymentDetails(
                $receivedPayload,
                $shopperReference,
            ),
        );

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

        $response = $this->getPaymentsApi()->payments(
            $this->clientPayloadFactory->createForSubmitPayment(
                $this->options,
                $redirectUrl,
                $receivedPayload,
                $order,
                $manualCapture,
                $customerIdentifier,
            ),
        );

        return $response->toArray();
    }

    public function requestCapture(
        PaymentInterface $payment,
    ): array {
        $response = $this->getModificationsApi()->captureAuthorisedPayment(
            $payment->getDetails()['pspReference'],
            $this->clientPayloadFactory->createForCapture($this->options, $payment),
        );

        return $response->toArray();
    }

    public function requestCancellation(
        PaymentInterface $payment,
    ): array {
        $response = $this->getModificationsApi()->cancelAuthorisedPaymentByPspReference(
            $payment->getDetails()['pspReference'],
            $this->clientPayloadFactory->createForCancel($this->options, $payment),
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
        $response = $this->getModificationsApi()->refundCapturedPayment(
            $payment->getDetails()['pspReference'],
            $this->clientPayloadFactory->createForRefund($this->options, $payment, $refund),
        );

        return $response->toArray();
    }

    public function requestReversal(PaymentInterface $payment): array
    {
        $response = $this->getModificationsApi()->refundOrCancelPayment(
            $payment->getDetails()['pspReference'],
            $this->clientPayloadFactory->createForReversal($this->options, $payment),
        );

        return $response->toArray();
    }

    public function generatePaymentLink(PaymentInterface $payment): array
    {
        $response = $this->getPaymentLinksApi()->paymentLinks(
            $this->clientPayloadFactory->createForPaymentLink($this->options, $payment),
        );

        return $response->toArray();
    }

    public function expirePaymentLink(string $paymentLinkId): array
    {
        $response = $this->getPaymentLinksApi()->updatePaymentLink(
            $paymentLinkId,
            $this->clientPayloadFactory->createForPaymentLinkExpiration($this->options, $paymentLinkId),
        );

        return $response->toArray();
    }

    public function submitPaypalPayments(array $receivedPayload, OrderInterface $order, string $returnUrl = ''): array
    {
        $response = $this->getPaymentsApi()->payments(
            $this->clientPayloadFactory->createForPaypalPayments(
                $this->options,
                $receivedPayload,
                $order,
                $returnUrl,
            ),
        );

        return $response->toArray();
    }

    public function updatesOrderForPaypalExpressCheckout(
        string $pspReference,
        string $paymentData,
        OrderInterface $order,
    ): array {
        $response = $this->getCheckoutUtilityApi()->updatesOrderForPaypalExpressCheckout(
            $this->clientPayloadFactory->createPaypalUpdateOrderRequest(
                $pspReference,
                $paymentData,
                $order,
            ),
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
