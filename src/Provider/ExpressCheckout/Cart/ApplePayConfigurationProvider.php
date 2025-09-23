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

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ApplePayConfigurationProvider implements ConfigurationProviderInterface
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
                'addressChange' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_apple_pay_shipping_address_change'),
                'optionsChange' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_apple_pay_shipping_options_change'),
                'checkout' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_apple_pay_checkout'),
                'payments' => $this->urlGenerator->generate('sylius_adyen_shop_payments'),
            ],
        ];
    }
}
