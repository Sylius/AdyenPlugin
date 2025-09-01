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
use Sylius\AdyenPlugin\Provider\ExpressCheckout\CountryProviderInterface;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

abstract class AbstractConfigurationAction
{
    /** @var array<ConfigurationProviderInterface> */
    private readonly array $configurationProviders;

    public function __construct(
        iterable $configurationProviders,
        private readonly CartContextInterface $cartContext,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentMethodsProviderInterface $paymentMethodsProvider,
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

        $paymentMethods = $this->paymentMethodsProvider->provideForOrder(AdyenClientProviderInterface::FACTORY_NAME, $order);

        $paymentMethod = $this->paymentMethodRepository->findOneAdyenByChannel($order->getChannel());
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);
        $config = $paymentMethod->getGatewayConfig()->getConfig();

        $configuration = [
            'paymentMethods' => $paymentMethods['paymentMethods'],
            'clientKey' => $config['clientKey'],
            'locale' => $order->getLocaleCode(),
            'environment' => $config['environment'],
            'allowedCountryCodes' => $this->countryProvider->getAllowedCountryCodes($order->getChannel()),
        ];

        $configuration = $this->configureShipping($configuration, $order, $request);

        /** @var ConfigurationProviderInterface $provider */
        foreach ($this->configurationProviders as $key => $provider) {
            $configuration[$key] = $provider->getConfiguration($order);
        }

        return new JsonResponse($configuration);
    }

    abstract protected function configureShipping(array $configuration, OrderInterface $order, Request $request): array;
}
