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

namespace Sylius\AdyenPlugin\Provider\ExpressCheckout\Cart;

use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaypalConfigurationProvider implements ConfigurationProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getConfiguration(OrderInterface $order): array
    {
        return [
            'amount' => [
                'value' => $order->getTotal(),
                'currency' => $order->getCurrencyCode(),
            ],
            'path' => [
                'initialize' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_paypal_initialize'),
                'addressChange' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_paypal_shipping_address_change'),
                'optionsChange' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_paypal_shipping_options_change'),
                'checkout' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_paypal_checkout'),
                'paymentDetails' => $this->urlGenerator->generate('sylius_adyen_shop_payment_details'),
            ],
        ];
    }
}
