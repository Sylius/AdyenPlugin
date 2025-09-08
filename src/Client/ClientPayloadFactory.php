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

use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\PaymentDetails;
use Adyen\Model\Checkout\PaymentLinkRequest;
use Adyen\Model\Checkout\PaymentReversalRequest;
use Adyen\Model\Checkout\PaypalUpdateOrderRequest;
use Adyen\Model\Checkout\UpdatePaymentLinkRequest;
use Payum\Core\Bridge\Spl\ArrayObject;
use Sylius\AdyenPlugin\Collector\CompositeEsdCollectorInterface;
use Sylius\AdyenPlugin\Entity\ShopperReferenceInterface;
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
        'onlineBanking_PL', // Przelewy24
    ];

    public function __construct(
        private readonly VersionResolverInterface $versionResolver,
        private readonly NormalizerInterface $normalizer,
        private readonly RequestStack $requestStack,
        private readonly CompositeEsdCollectorInterface $esdCollector,
        private readonly PaypalUpdateOrderRequestFactoryInterface $paypalUpdateOrderRequestFactory,
    ) {
        $this->allowedMethodsList = array_values(array_unique(array_merge(
            $this->allowedMethodsList,
            (new PaymentDetails())->getTypeAllowableValues(),
        )));
    }

    public function createForAvailablePaymentMethods(
        ArrayObject $options,
        OrderInterface $order,
        ?ShopperReferenceInterface $shopperReference = null,
    ): array {
        $address = $order->getBillingAddress();
        $countryCode = $address?->getCountryCode() ?? '';
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->getLocale() ?? '';

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

        $payload = $this->injectShopperReference($payload, $shopperReference);
        $payload = $this->enableOneOffPaymentIfApplicable($payload, $shopperReference);
        $payload = $this->versionResolver->appendVersionConstraints($payload);

        return $payload;
    }

    public function createForPaymentDetails(
        array $receivedPayload,
        ?ShopperReferenceInterface $shopperReference = null,
    ): array {
        $payload = $this->injectShopperReference($receivedPayload, $shopperReference);
        $payload = $this->enableOneOffPaymentIfApplicable($payload, $shopperReference);
        $payload = $this->versionResolver->appendVersionConstraints($payload);

        return $payload;
    }

    public function createForSubmitPayment(
        ArrayObject $options,
        string $url,
        array $receivedPayload,
        OrderInterface $order,
        bool $manualCapture = false,
        ?ShopperReferenceInterface $shopperReference = null,
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
            'shopperName' => [
                'firstName' => $billingAddress?->getFirstName() ?? '',
                'lastName' => $billingAddress?->getLastName() ?? '',
            ],
        ];

        $payload = $this->add3DSecureFlags($receivedPayload, $payload);

        $payload = $this->filterArray($receivedPayload, [
            'browserInfo', 'paymentMethod', 'clientStateDataIndicator', 'riskData',
        ]) + $payload;

        $payload = $this->injectShopperReference($payload, $shopperReference);
        $payload = $this->enableOneOffPaymentIfApplicable(
            $payload,
            $shopperReference,
            (bool) ($receivedPayload['storePaymentMethod'] ?? false),
        );
        $payload = $this->versionResolver->appendVersionConstraints($payload);

        $payload = $payload + $this->getOrderDataForPayment($order);

        $payload = $this->addManualCaptureIfApplicable($payload, $manualCapture);
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
        ShopperReferenceInterface $shopperReference,
    ): array {
        $params = [
            'merchantAccount' => $options['merchantAccount'],
            'recurringDetailReference' => $paymentReference,
            'shopperReference' => $shopperReference->getIdentifier(),
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

    public function createForReversal(ArrayObject $options, PaymentInterface $payment): PaymentReversalRequest
    {
        $payload = [
            'merchantAccount' => $options['merchantAccount'],
            'reference' => $payment->getOrder()->getNumber(),
        ];

        $payload = $this->versionResolver->appendVersionConstraints($payload);

        return new PaymentReversalRequest($payload);
    }

    public function createForPaymentLink(ArrayObject $options, PaymentInterface $payment): PaymentLinkRequest
    {
        $order = $payment->getOrder();
        Assert::notNull($order);

        $payload = $this->getOrderDataForPayment($order);
        $payload = $payload + [
            'amount' => [
                'value' => $order->getTotal(),
                'currency' => $order->getCurrencyCode(),
            ],
            'reference' => (string) $order->getNumber(),
            'countryCode' => $order->getBillingAddress()?->getCountryCode() ?? self::NO_COUNTRY_AVAILABLE_PLACEHOLDER,
            'merchantAccount' => $options['merchantAccount'],
        ];

        if ($order->getLocaleCode() !== null) {
            $payload['shopperLocale'] = str_replace('_', '-', $order->getLocaleCode());
        }

        unset($payload['shopperIp']);

        $payload = $this->versionResolver->appendVersionConstraints($payload);

        return new PaymentLinkRequest($payload);
    }

    public function createForPaymentLinkExpiration(ArrayObject $options, string $paymentLinkId): UpdatePaymentLinkRequest
    {
        $request = new UpdatePaymentLinkRequest();
        $request->setStatus('expired');

        return $request;
    }

    public function createForPaypalPayments(ArrayObject $options, array $receivedPayload, OrderInterface $order, bool $manualCapture = false, string $returnUrl = ''): array
    {
        $payload = [
            'merchantAccount' => $options['merchantAccount'],
            'amount' => new Amount([
                'currency' => $order->getCurrencyCode(),
                'value' => $order->getItemsSubtotal(),
            ]),
            'reference' => $order->getNumber(),
            'returnUrl' => $returnUrl,
        ];

        $payload = $this->versionResolver->appendVersionConstraints($payload);
        $payload = $this->addManualCaptureIfApplicable($payload, $manualCapture );

        return array_merge($payload, $receivedPayload);
    }

    public function createPaypalUpdateOrderRequest(
        string $pspReference,
        string $paymentData,
        OrderInterface $order,
    ): PaypalUpdateOrderRequest {
        return $this->paypalUpdateOrderRequestFactory->create(
            $pspReference,
            $paymentData,
            $order,
        );
    }

    private function filterArray(array $payload, array $keysWhitelist): array
    {
        return array_filter($payload, fn ($key): bool => in_array($key, $keysWhitelist, true), \ARRAY_FILTER_USE_KEY);
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

    private function isTokenizationSupported(array $payload, ?ShopperReferenceInterface $customerIdentifier): bool
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
        ?ShopperReferenceInterface $customerIdentifier,
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
        ?ShopperReferenceInterface $customerIdentifier,
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

    private function addManualCaptureIfApplicable(array $payload, bool $manualCapture): array
    {
        if (false === $manualCapture) {
            return $payload;
        }

        $payload['additionalData'] = array_merge($payload['additionalData'] ?? [], [
            'manualCapture' => 'true',
        ]);

        return $payload;
    }
}
