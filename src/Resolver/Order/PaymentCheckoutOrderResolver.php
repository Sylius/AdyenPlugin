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
        /**
         * @var string|null $tokenValue
         */
        $tokenValue = $this->getCurrentRequest()->get('tokenValue');

        if (null === $tokenValue) {
            return null;
        }

        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);

        return $order;
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
