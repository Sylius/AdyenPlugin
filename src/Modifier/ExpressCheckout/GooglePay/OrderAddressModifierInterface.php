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

namespace Sylius\AdyenPlugin\Modifier\ExpressCheckout\GooglePay;

use Sylius\Component\Core\Model\OrderInterface;

interface OrderAddressModifierInterface
{
    public function modify(OrderInterface $order, array $addressData): void;

    public function modifyTemporaryAddress(OrderInterface $order, array $addressData): void;
}
