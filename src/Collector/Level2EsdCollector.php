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

namespace Sylius\AdyenPlugin\Collector;

use Sylius\Component\Core\Model\OrderInterface;

final class Level2EsdCollector implements EsdCollectorInterface
{
    public function supports(string $merchantCategoryCode): bool
    {
        // Level 2 is the fallback for any MCC that doesn't have specific industry support
        // This will be handled by the composite collector logic
        return false;
    }

    public function collect(OrderInterface $order): array
    {
        $customer = $order->getCustomer();

        return [
            'enhancedSchemeData.customerReference' => $customer !== null ? (string) $customer->getId() : (string) $order->getId(),
            'enhancedSchemeData.totalTaxAmount' => $order->getTaxTotal(),
        ];
    }
}
