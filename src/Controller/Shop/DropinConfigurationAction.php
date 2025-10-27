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

namespace Sylius\AdyenPlugin\Controller\Shop;

use Sylius\AdyenPlugin\Callback\PreserveOrderTokenUponRedirectionCallback;
use Sylius\AdyenPlugin\Provider\DropinConfigurationProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DropinConfigurationAction
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private readonly DropinConfigurationProviderInterface $dropinConfigurationProvider,
        private readonly CartContextInterface $cartContext,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(
        Request $request,
        string $code,
        ?string $orderToken = null,
    ): JsonResponse {
        $order = $this->getOrder($orderToken);

        if (null === $order || null === $order->getId()) {
            return $this->getResponseForDroppedOrder($request);
        }

        return new JsonResponse($this->dropinConfigurationProvider->getConfiguration($order, $code));
    }

    private function getOrder(?string $orderToken = null): ?OrderInterface
    {
        if (null === $orderToken) {
            $order = $this->cartContext->getCart();
        } else {
            $order = $this->orderRepository->findOneByTokenValue($orderToken) ?? $this->orderRepository->findCartByTokenValue($orderToken);
        }

        /** @var OrderInterface|null $result */
        $result = $order;

        return $result;
    }

    private function getResponseForDroppedOrder(Request $request): JsonResponse
    {
        /** @var string|null $tokenValue */
        $tokenValue = $request->getSession()->get(
            PreserveOrderTokenUponRedirectionCallback::NON_FINALIZED_CART_SESSION_KEY,
        );

        try {
            if (null === $tokenValue) {
                throw new NotFoundHttpException();
            }
        } finally {
            $request->getSession()->remove(
                PreserveOrderTokenUponRedirectionCallback::NON_FINALIZED_CART_SESSION_KEY,
            );
        }

        return new JsonResponse([
            'redirect' => $this->urlGenerator->generate('sylius_shop_order_show', [
                'tokenValue' => $tokenValue,
            ]),
        ]);
    }
}
