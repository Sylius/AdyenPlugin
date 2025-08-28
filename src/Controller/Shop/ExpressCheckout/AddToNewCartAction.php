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

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\OrderBundle\Controller\AddToCartCommandInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\NewResourceFactoryInterface;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfigurationFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\TokenAssigner\OrderTokenAssignerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Resource\Metadata\MetadataInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddToNewCartAction
{
    public function __construct(
        private readonly AddToCartCommandFactoryInterface $addToCartCommandFactory,
        private readonly CartContextInterface $cartContext,
        private readonly EntityManagerInterface $cartManager,
        private readonly FactoryInterface $factory,
        private readonly FormFactoryInterface $formFactory,
        private readonly MetadataInterface $metadata,
        private readonly NewResourceFactoryInterface $newResourceFactory,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly RequestConfigurationFactoryInterface $requestConfigurationFactory,
        private readonly OrderTokenAssignerInterface $orderTokenAssigner,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var OrderInterface $cart */
        $cart = $this->cartContext->getCart();
        $configuration = $this->requestConfigurationFactory->create($this->metadata, $request);

        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->newResourceFactory->create($configuration, $this->factory);

        $this->quantityModifier->modify($orderItem, 1);
        /** @var string $formType */
        $formType = $configuration->getFormType();

        $form = $this->formFactory->create(
            $formType,
            $this->addToCartCommandFactory->createWithCartAndCartItem($cart, $orderItem),
            $configuration->getFormOptions(),
        );

        $form = $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $product = $orderItem->getVariant()->getProduct();

            return new JsonResponse([
                'error' => true,
                'message' => sprintf('Cannot add product %s to cart', $product->getName()),
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var AddToCartCommandInterface $addToCartCommand */
        $addToCartCommand = $form->getData();

        $this->orderModifier->addToOrder($addToCartCommand->getCart(), $addToCartCommand->getCartItem());
        $this->orderTokenAssigner->assignTokenValueIfNotSet($cart);

        $this->cartManager->persist($cart);
        $this->cartManager->flush();

        return new JsonResponse(['orderToken' => $cart->getTokenValue()]);
    }
}
