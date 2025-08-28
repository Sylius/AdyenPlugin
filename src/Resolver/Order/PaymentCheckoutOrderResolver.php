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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PaymentCheckoutOrderResolver implements PaymentCheckoutOrderResolverInterface
{
    /** @var RequestStack */
    private $requestStack;

    /** @var CartContextInterface */
    private $cartContext;

    private readonly OrderRepositoryInterface $orderRepository;

    public function __construct(
        RequestStack $requestStack,
        CartContextInterface $cartContext,
        OrderRepositoryInterface $orderRepository,
    ) {
        $this->requestStack = $requestStack;
        $this->cartContext = $cartContext;
        $this->orderRepository = $orderRepository;
    }

    private function getCurrentRequest(): Request
    {
        $result = $this->requestStack->getCurrentRequest();
        if (null === $result) {
            throw new BadRequestException('No request provided');
        }

        return $result;
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

    public function resolve(): OrderInterface
    {
        $order = $this->getCurrentOrder();

        if (!$order instanceof OrderInterface) {
            $order = $this->cartContext->getCart();
        }

        if ($order instanceof OrderInterface) {
            return $order;
        }

        throw new NotFoundHttpException('Order was not found');
    }
}
