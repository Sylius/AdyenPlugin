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

final class Level3EsdCollector implements EsdCollectorInterface
{
    private EsdCollectorInterface $level2Collector;

    private ItemDetailLineCollectorInterface $itemDetailLineCollector;

    public function __construct(
        EsdCollectorInterface $level2Collector,
        ItemDetailLineCollectorInterface $itemDetailLineCollector,
    ) {
        $this->level2Collector = $level2Collector;
        $this->itemDetailLineCollector = $itemDetailLineCollector;
    }

    public function supports(string $merchantCategoryCode): bool
    {
        // Level 3 is the default for any MCC that doesn't have specific industry support
        // This will be handled by the composite collector logic
        return true;
    }

    public function collect(OrderInterface $order): array
    {
        $data = $this->level2Collector->collect($order);

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $data['enhancedSchemeData.destinationPostalCode'] = (string) $shippingAddress->getPostcode();
            $data['enhancedSchemeData.destinationCountryCode'] = (string) $shippingAddress->getCountryCode();

            if ($shippingAddress->getProvinceCode()) {
                $data['enhancedSchemeData.destinationStateProvinceCode'] = $shippingAddress->getProvinceCode();
            }
        }

        $data['enhancedSchemeData.freightAmount'] = $order->getShippingTotal();
        $data['enhancedSchemeData.orderDate'] = $order->getCreatedAt()->format('Ymd');

        foreach ($order->getItems() as $index => $item) {
            $data = array_merge($data, $this->itemDetailLineCollector->collect($item, $index + 1));
        }

        return $data;
    }
}
