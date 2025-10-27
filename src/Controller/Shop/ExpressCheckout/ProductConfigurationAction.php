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

use Sylius\AdyenPlugin\Provider\ExpressCheckout\Cart\ConfigurationProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\CountryProviderInterface;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProviderInterface;
use Sylius\AdyenPlugin\Repository\Query\AdyenPaymentMethodQueryInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductConfigurationAction extends AbstractConfigurationAction
{
    /**
     * @param iterable<ConfigurationProviderInterface> $configurationProviders
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     * @param CartItemFactoryInterface<OrderItemInterface> $cartItemFactory
     */
    public function __construct(
        iterable $configurationProviders,
        CartContextInterface $cartContext,
        AdyenPaymentMethodQueryInterface $adyenPaymentMethodQuery,
        PaymentMethodsProviderInterface $paymentMethodsProvider,
        CountryProviderInterface $countryProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CartItemFactoryInterface $cartItemFactory,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly OrderModifierInterface $orderModifier,
    ) {
        parent::__construct($configurationProviders, $cartContext, $adyenPaymentMethodQuery, $paymentMethodsProvider, $countryProvider);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $productId = $request->query->get('productId');
        if (null === $productId) {
            return new JsonResponse(['error' => 'Product id is required'], 400);
        }

        /** @var ProductInterface|null $product */
        $product = $this->productRepository->find($productId);
        if (null === $product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

        $this->addProductToOrder($product);

        return parent::__invoke($request);
    }

    protected function configureShipping(array $configuration, OrderInterface $order, Request $request): array
    {
        $configuration['path'] = [
            'addToNewCart' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_add_to_new_cart', ['productId' => '_PRODUCT_ID_']),
            'removeCart' => $this->urlGenerator->generate('sylius_adyen_shop_express_checkout_remove_cart', ['tokenValue' => '_TOKEN_VALUE_']),
        ];

        return $configuration;
    }

    private function addProductToOrder(ProductInterface $product): void
    {
        /** @var OrderInterface $order */
        $order = $this->cartContext->getCart();

        $cartItem = $this->cartItemFactory->createForProduct($product);
        $this->quantityModifier->modify($cartItem, 1);
        $this->orderModifier->addToOrder($order, $cartItem);
    }
}
