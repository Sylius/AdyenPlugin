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

namespace Sylius\AdyenPlugin\Modifier\ExpressCheckout;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderCustomerModifierInterface
{
    public function modify(OrderInterface $order, ?string $email): void;
}
