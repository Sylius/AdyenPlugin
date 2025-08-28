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

use Sylius\AdyenPlugin\Entity\CommodityCodeAwareInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class ItemDetailLineCollector implements ItemDetailLineCollectorInterface
{
    public const UNIT_OF_MEASURE = 'PCS';

    public function collect(OrderItemInterface $orderItem, int $lineNumber): array
    {
        $data = [];
        /** @var ProductVariantInterface|CommodityCodeAwareInterface $variant */
        $variant = $orderItem->getVariant();
        $product = $variant !== null ? $variant->getProduct() : null;

        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.productCode'] = $variant !== null ? $variant->getCode() : ($product !== null ? $product->getCode() : 'UNKNOWN');
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.description'] = substr((string) $orderItem->getProductName(), 0, 26);
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.quantity'] = (string) $orderItem->getQuantity();
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.unitOfMeasure'] = self::UNIT_OF_MEASURE;
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.unitPrice'] = (string) $orderItem->getUnitPrice();
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.totalAmount'] = (string) $orderItem->getTotal();

        $commodityCode = $variant instanceof CommodityCodeAwareInterface ? $variant->getCommodityCode() : null;
        if ($commodityCode !== null) {
            $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.commodityCode'] = $commodityCode;
        }

        $discountAmount =
            $orderItem->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT) +
            $orderItem->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT)
        ;
        if ($discountAmount < 0) {
            $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.discountAmount'] = (string) abs($discountAmount);
        }

        return $data;
    }
}
