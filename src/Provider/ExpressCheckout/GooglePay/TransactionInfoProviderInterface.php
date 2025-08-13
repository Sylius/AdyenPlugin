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

namespace Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay;

use Sylius\Component\Core\Model\OrderInterface;

interface TransactionInfoProviderInterface
{
    public const TOTAL_PRICE_STATUS_FINAL = 'FINAL';

    public const DISPLAY_ITEM_TYPE_SUBTOTAL = 'SUBTOTAL';

    public const DISPLAY_ITEM_TYPE_DISCOUNT = 'DISCOUNT';

    public const DISPLAY_ITEM_TYPE_SHIPPING_OPTION = 'SHIPPING_OPTION';

    public const DISPLAY_ITEM_TYPE_TAX = 'TAX';

    public function provide(OrderInterface $order): array;
}
