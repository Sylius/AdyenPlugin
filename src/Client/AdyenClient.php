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

use Adyen\Client;
use Adyen\Service\Checkout;
use Adyen\Service\Checkout\ModificationsApi;
use Adyen\Service\Checkout\PaymentLinksApi;
use Adyen\Service\Modification;
use Adyen\Service\Recurring;
use Payum\Core\Bridge\Spl\ArrayObject;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Model\PaymentOutcome;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;
use Webmozart\Assert\Assert;

final class AdyenClient implements AdyenClientInterface
{
    private readonly ArrayObject $options;

    private readonly Client $transport;

    public function __construct(
        array $options,
        AdyenTransportFactoryInterface $adyenTransportFactory,
        private readonly ClientPayloadFactoryInterface $clientPayloadFactory,
        private readonly PaymentMethodsFilterInterface $paymentMethodsFilter,
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

    public function getAvailablePaymentMethods(
        OrderInterface $order,
        ?AdyenTokenInterface $adyenToken = null,
    ): array {
        $paymentMethods = (array) $this->getCheckout()->paymentMethods(
            $this->clientPayloadFactory->createForAvailablePaymentMethods($this->options, $order, $adyenToken),
        );

        Assert::keyExists($paymentMethods, 'paymentMethods');

        return $this->paymentMethodsFilter->filter($paymentMethods);
    }

    public function paymentDetails(
        array $receivedPayload,
        ?AdyenTokenInterface $adyenToken = null,
    ): array {
        $payload = $this->clientPayloadFactory->createForPaymentDetails(
            $receivedPayload,
            $adyenToken,
        );

        return (array) $this->getCheckout()->paymentsDetails($payload);
    }

    public function submitPayment(
        string $redirectUrl,
        array $receivedPayload,
        OrderInterface $order,
        ?AdyenTokenInterface $customerIdentifier = null,
    ): array {
        if (!isset($receivedPayload['paymentMethod'])) {
            throw new \InvalidArgumentException();
        }

        $payload = $this->clientPayloadFactory->createForSubmitPayment(
            $this->options,
            $redirectUrl,
            $receivedPayload,
            $order,
            $customerIdentifier,
        );

        return (array) $this->getCheckout()->payments($payload);
    }

    public function requestCapture(
        PaymentInterface $payment,
    ): array {
        $params = $this->clientPayloadFactory->createForCapture($this->options, $payment);

        return (array) $this->getModification()->capture($params);
    }

    public function requestCancellation(
        PaymentInterface $payment,
    ): array {
        $params = $this->clientPayloadFactory->createForCancel($this->options, $payment);

        return (array) $this->getModification()->cancel($params);
    }

    public function removeStoredToken(
        string $paymentReference,
        AdyenTokenInterface $adyenToken,
    ): array {
        $params = $this->clientPayloadFactory->createForTokenRemove($this->options, $paymentReference, $adyenToken);

        return (array) $this->getRecurring()->disable($params);
    }

    public function requestRefund(
        PaymentInterface $payment,
        RefundPaymentGenerated $refund,
    ): array {
        $params = $this->clientPayloadFactory->createForRefund($this->options, $payment, $refund);

        return (array) $this->getModification()->refund($params);
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

    public function getEnvironment(): string
    {
        return (string) $this->options['environment'];
    }

    private function getCheckout(): Checkout
    {
        return new Checkout(
            $this->transport,
        );
    }

    private function getPaymentLinksApi(): PaymentLinksApi
    {
        return new PaymentLinksApi($this->transport);
    }

    private function getModificationsApi(): ModificationsApi
    {
        return new ModificationsApi($this->transport);
    }

    private function getModification(): Modification
    {
        return new Modification(
            $this->transport,
        );
    }

    private function getRecurring(): Recurring
    {
        return new Recurring(
            $this->transport,
        );
    }
}
