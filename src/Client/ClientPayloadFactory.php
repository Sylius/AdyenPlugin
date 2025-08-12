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

use Payum\Core\Bridge\Spl\ArrayObject;
use Sylius\AdyenPlugin\Collector\CompositeEsdCollectorInterface;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Normalizer\AbstractPaymentNormalizer;
use Sylius\AdyenPlugin\Resolver\Version\VersionResolverInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

final class ClientPayloadFactory implements ClientPayloadFactoryInterface
{
    /** @var string[] */
    private array $allowedMethodsList = [
        'ideal',
        'paypal',
        'directEbanking', // Klarna - Sofort
        'applepay',
        'googlepay',
        'alipay',
        'twint',
        'bizum',
        'blik',
        'dotpay',
        'scheme',
        'klarna',
        'klarna_account',
        'klarna_paynow',
        'bcmc',
        'bcmc_mobile',
        'benefit',
        'knet',
        'naps',
        'omannet',
        'ach',
        'directdebit_GB',
        'ratepay_directdebit',
        'wechatpayWeb',
        'wechatpaySDK',
        'wechatpayQR',
    ];

    public function __construct(
        private readonly VersionResolverInterface $versionResolver,
        private readonly NormalizerInterface $normalizer,
        private readonly RequestStack $requestStack,
        private readonly CompositeEsdCollectorInterface $esdCollector,
    ) {
    }

    public function createForAvailablePaymentMethods(
        ArrayObject $options,
        OrderInterface $order,
        ?AdyenTokenInterface $adyenToken = null,
    ): array {
        $address = $order->getBillingAddress();
        $countryCode = null !== $address ? (string) $address->getCountryCode() : '';
        $request = $this->requestStack->getCurrentRequest();
        $locale = null !== $request ? $request->getLocale() : '';

        $payload = [
            'amount' => [
                'value' => $order->getTotal(),
                'currency' => (string) $order->getCurrencyCode(),
            ],
            'merchantAccount' => $options['merchantAccount'],
            'countryCode' => $countryCode,
            'shopperLocale' => $locale,
            'channel' => 'Web',
            'allowedPaymentMethods' => $this->allowedMethodsList,
        ];

        $payload = $this->injectShopperReference($payload, $adyenToken);
        $payload = $this->enableOneOffPaymentIfApplicable($payload, $adyenToken);
        $payload = $this->versionResolver->appendVersionConstraints($payload);

        return $payload;
    }

    public function createForPaymentDetails(
        array $receivedPayload,
        ?AdyenTokenInterface $adyenToken = null,
    ): array {
        $payload = $this->injectShopperReference($receivedPayload, $adyenToken);
        $payload = $this->enableOneOffPaymentIfApplicable($payload, $adyenToken);
        $payload = $this->versionResolver->appendVersionConstraints($payload);

        return $payload;
    }

    public function createForSubmitPayment(
        ArrayObject $options,
        string $url,
        array $receivedPayload,
        OrderInterface $order,
        ?AdyenTokenInterface $adyenToken = null,
    ): array {
        $billingAddress = $order->getBillingAddress();
        $countryCode = null !== $billingAddress
            ? (string) $billingAddress->getCountryCode()
            : null
        ;

        $payload = [
            'amount' => [
                'value' => $order->getTotal(),
                'currency' => $order->getCurrencyCode(),
            ],
            'reference' => (string) $order->getNumber(),
            'merchantAccount' => $options['merchantAccount'],
            'returnUrl' => $url,

            'channel' => 'web',
            'origin' => $this->getOrigin($url),
            'countryCode' => $countryCode,
        ];

        $payload = $this->add3DSecureFlags($receivedPayload, $payload);

        $payload = $this->filterArray($receivedPayload, [
            'browserInfo', 'paymentMethod', 'clientStateDataIndicator', 'riskData',
        ]) + $payload;

        $payload = $this->injectShopperReference($payload, $adyenToken);
        $payload = $this->enableOneOffPaymentIfApplicable(
            $payload,
            $adyenToken,
            (bool) ($receivedPayload['storePaymentMethod'] ?? false),
        );
        $payload = $this->versionResolver->appendVersionConstraints($payload);

        $payload = $payload + $this->getOrderDataForPayment($order);
        $payload = $this->addEsdIfApplicable($payload, $options, $order);

        return $payload;
    }

    public function createForCapture(
        ArrayObject $options,
        PaymentInterface $payment,
    ): array {
        $payload = [
            'merchantAccount' => $options['merchantAccount'],
            'modificationAmount' => [
                'value' => $payment->getAmount(),
                'currency' => (string) $payment->getCurrencyCode(),
            ],
            'originalReference' => $payment->getDetails()['pspReference'],
        ];

        $payload = $this->versionResolver->appendVersionConstraints($payload);

        $order = $payment->getOrder();
        if ($order instanceof OrderInterface) {
            $payload = $this->addEsdIfApplicable($payload, $options, $order, $payment);
        }

        return $payload;
    }

    public function createForCancel(
        ArrayObject $options,
        PaymentInterface $payment,
    ): array {
        $params = [
            'merchantAccount' => $options['merchantAccount'],
            'originalReference' => $payment->getDetails()['pspReference'],
        ];

        $params = $this->versionResolver->appendVersionConstraints($params);

        return $params;
    }

    public function createForTokenRemove(
        ArrayObject $options,
        string $paymentReference,
        AdyenTokenInterface $adyenToken,
    ): array {
        $params = [
            'merchantAccount' => $options['merchantAccount'],
            'recurringDetailReference' => $paymentReference,
            'shopperReference' => $adyenToken->getIdentifier(),
        ];

        $params = $this->versionResolver->appendVersionConstraints($params);

        return $params;
    }

    public function createForRefund(
        ArrayObject $options,
        PaymentInterface $payment,
        RefundPaymentGenerated $refund,
    ): array {
        $order = $payment->getOrder();
        Assert::notNull($order);

        $params = [
            'merchantAccount' => $options['merchantAccount'],
            'modificationAmount' => [
                'value' => $refund->amount(),
                'currency' => $refund->currencyCode(),
            ],
            'reference' => (string) $order->getNumber(),
            'originalReference' => $payment->getDetails()['pspReference'],
        ];

        $params = $this->versionResolver->appendVersionConstraints($params);

        return $params;
    }

    private function filterArray(array $payload, array $keysWhitelist): array
    {
        return array_filter($payload, function ($key) use ($keysWhitelist): bool {
            return in_array($key, $keysWhitelist, true);
        }, \ARRAY_FILTER_USE_KEY);
    }

    private function getOrderDataForPayment(OrderInterface $order): array
    {
        return (array) $this->normalizer->normalize(
            $order,
            null,
            [AbstractPaymentNormalizer::NORMALIZER_ENABLED => true],
        );
    }

    private function getOrigin(string $url): string
    {
        $components = parse_url($url);

        $pattern = '%s://%s';
        if (isset($components['port'])) {
            $pattern .= ':%d';
        }

        return sprintf(
            $pattern,
            $components[AdyenClientInterface::CREDIT_CARD_TYPE] ?? '',
            $components['host'] ?? '',
            $components['port'] ?? 0,
        );
    }

    private function isTokenizationSupported(array $payload, ?AdyenTokenInterface $customerIdentifier): bool
    {
        if (null === $customerIdentifier) {
            return false;
        }

        if (
            isset($payload['paymentMethod']['type']) &&
            AdyenClientInterface::CREDIT_CARD_TYPE !== $payload['paymentMethod']['type']
        ) {
            return false;
        }

        return true;
    }

    private function injectShopperReference(
        array $payload,
        ?AdyenTokenInterface $customerIdentifier,
    ): array {
        if (null !== $customerIdentifier) {
            $payload['shopperReference'] = $customerIdentifier->getIdentifier();
        }

        return $payload;
    }

    private function add3DSecureFlags(array $receivedPayload, array $payload): array
    {
        if (
            isset($receivedPayload['paymentMethod']['type']) &&
            'scheme' == $receivedPayload['paymentMethod']['type']
        ) {
            $payload['additionalData'] = [
                'allow3DS2' => true,
            ];
        }

        return $payload;
    }

    private function enableOneOffPaymentIfApplicable(
        array $payload,
        ?AdyenTokenInterface $customerIdentifier,
        bool $store = false,
    ): array {
        if (!$this->isTokenizationSupported($payload, $customerIdentifier)) {
            return $payload;
        }

        if ($store) {
            $payload['storePaymentMethod'] = true;
        }

        $payload['recurringProcessingModel'] = 'CardOnFile';
        $payload['shopperInteraction'] = 'Ecommerce';

        return $payload;
    }

    private function addEsdIfApplicable(
        array $payload,
        ArrayObject $options,
        OrderInterface $order,
        ?PaymentInterface $payment = null,
    ): array {
        $gatewayConfig = $options->getArrayCopy();

        $esd = $this->esdCollector->collect($order, $gatewayConfig, $payload, $payment);
        if ($esd === []) {
            return $payload;
        }

        $payload['additionalData'] = array_merge($payload['additionalData'] ?? [], $esd);

        return $payload;
    }
}
