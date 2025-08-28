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

use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\Cart\ConfigurationProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\CountryProviderInterface;
use Sylius\AdyenPlugin\Provider\PaymentMethodsForOrderProvider;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class CartConfigurationAction
{
    /** @var array<ConfigurationProviderInterface> */
    private readonly array $configurationProviders;

    public function __construct(
        iterable $configurationProviders,
        private readonly CartContextInterface $cartContext,
        private readonly PaymentMethodsForOrderProvider $paymentMethodsForOrderProvider,
        private readonly CountryProviderInterface $countryProvider,
    ) {
        Assert::allIsInstanceOf(
            $configurationProviders,
            ConfigurationProviderInterface::class,
            sprintf('All configuration providers must implement "%s".', ConfigurationProviderInterface::class),
        );
        $this->configurationProviders = $configurationProviders instanceof \Traversable ? iterator_to_array($configurationProviders) : $configurationProviders;
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var OrderInterface $order */
        $order = $this->cartContext->getCart();

        $config = $this->paymentMethodsForOrderProvider->provideConfiguration($order, AdyenClientProviderInterface::FACTORY_NAME);
        Assert::isArray($config);

        $configuration = [
            'paymentMethods' => $config['paymentMethods'],
            'clientKey' => $config['clientKey'],
            'locale' => $order->getLocaleCode(),
            'environment' => $config['environment'],
            'shippingOptionRequired' => $order->isShippingRequired(),
            'allowedCountryCodes' => $this->countryProvider->getAllowedCountryCodes($order->getChannel()),
        ];

        /** @var ConfigurationProviderInterface $provider */
        foreach ($this->configurationProviders as $key => $provider) {
            $configuration[$key] = $provider->getConfiguration($order);
        }

        return new JsonResponse($configuration);
    }
}
