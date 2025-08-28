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

use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RemoveCartAction
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->orderRepository->findCartByTokenValue($request->get('tokenValue', ''));
        if (null === $order) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        $this->orderRepository->remove($order);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
