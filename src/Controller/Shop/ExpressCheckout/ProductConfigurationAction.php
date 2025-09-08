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

namespace Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout;

use Sylius\AdyenPlugin\Provider\ExpressCheckout\CountryProviderInterface;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductConfigurationAction extends AbstractConfigurationAction
{
    public function __construct(
        iterable $configurationProviders,
        CartContextInterface $cartContext,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        PaymentMethodsProviderInterface $paymentMethodsProvider,
        CountryProviderInterface $countryProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($configurationProviders, $cartContext, $paymentMethodRepository, $paymentMethodsProvider, $countryProvider);
    }

    protected function configureShipping(array $configuration, OrderInterface $order, Request $request): array
    {
        $configuration['path'] = [
            'addToNewCart' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_add_to_new_cart', ['productId' => '_PRODUCT_ID_']),
            'removeCart' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_remove_cart', ['tokenValue' => '_TOKEN_VALUE_']),
        ];

        return $configuration;
    }
}
