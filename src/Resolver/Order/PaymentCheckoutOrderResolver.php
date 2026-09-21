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

namespace Sylius\AdyenPlugin\Resolver\Order;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PaymentCheckoutOrderResolver implements PaymentCheckoutOrderResolverInterface
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CartContextInterface $cartContext,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function resolve(): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->getCurrentOrder() ?? $this->cartContext->getCart();

        return $order;
    }

    private function getCurrentOrder(): ?OrderInterface
    {
        $tokenValue = $this->resolveTokenValue($this->getCurrentRequest());

        if (null === $tokenValue) {
            return null;
        }

        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);

        return $order;
    }

    /**
     * Symfony 8 removed Request::get(), which looked the key up in the attributes,
     * then the query string and finally the request body - in that order.
     */
    private function resolveTokenValue(Request $request): ?string
    {
        foreach ([$request->attributes, $request->query, $request->request] as $parameters) {
            if ($parameters->has('tokenValue')) {
                return $parameters->getString('tokenValue');
            }
        }

        return null;
    }

    private function getCurrentRequest(): Request
    {
        $result = $this->requestStack->getCurrentRequest();
        if (null === $result) {
            throw new BadRequestException('No request provided');
        }

        return $result;
    }
}
