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

namespace Tests\Sylius\AdyenPlugin\Functional\Service;

use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Model\OrderInterface;

final class TestCartContext implements CartContextInterface
{
    public function __construct(
        private CartContextInterface $fallbackContext,
    ) {
    }

    private ?OrderInterface $order = null;

    public function setOrder(OrderInterface $order): void
    {
        $this->order = $order;
    }

    public function getCart(): OrderInterface
    {
        if (null === $this->order) {
            return $this->fallbackContext->getCart();
        }

        return $this->order;
    }
}
