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
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\TransactionInfoProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GooglePayConfigurationProvider implements ConfigurationProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TransactionInfoProviderInterface $transactionInfoProvider,
    ) {
    }

    public function getConfiguration(OrderInterface $order): array
    {
        return [
            'path' => [
                'shippingOptions' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_google_pay_shipping_options'),
                'checkout' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_google_pay_checkout'),
                'payments' => $this->urlGenerator->generate('sylius_adyen_shop_payments'),
            ],
            'transactionInfo' => $this->transactionInfoProvider->provide($order),
        ];
    }
}
