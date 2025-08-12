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

namespace Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\GooglePay;

use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\CountryProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\TransactionInfoProviderInterface;
use Sylius\AdyenPlugin\Provider\PaymentMethodsForOrderProvider;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

class CartConfigurationAction
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly PaymentMethodsForOrderProvider $paymentMethodsForOrderProvider,
        private readonly CountryProviderInterface $countryProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TransactionInfoProviderInterface $transactionInfoProvider,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var OrderInterface $order */
        $order = $this->cartContext->getCart();

        $config = $this->paymentMethodsForOrderProvider->provideConfiguration($order, AdyenClientProviderInterface::FACTORY_NAME);
        Assert::isArray($config);

        return new JsonResponse([
            'paymentMethods' => $config['paymentMethods'],
            'clientKey' => $config['clientKey'],
            'locale' => $order->getLocaleCode(),
            'environment' => $config['environment'],
            'shippingOptionRequired' => $order->isShippingRequired(),
            'transactionInfo' => $this->transactionInfoProvider->provide($order),
            'allowedCountryCodes' => $this->countryProvider->getAllowedCountryCodes($order),
            'path' => [
                'shippingOptions' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_google_pay_shipping_options'),
                'checkout' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_google_pay_checkout'),
                'payments' => $this->urlGenerator->generate('sylius_adyen_shop_payments', ['code' => AdyenClientProviderInterface::FACTORY_NAME]),
            ],
        ]);
    }
}
