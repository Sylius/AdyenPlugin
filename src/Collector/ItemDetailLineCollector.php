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

use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class ItemDetailLineCollector implements ItemDetailLineCollectorInterface
{
    public function collect(OrderItemInterface $orderItem, int $lineNumber): array
    {
        $data = [];
        $variant = $orderItem->getVariant();
        $product = $variant ? $variant->getProduct() : null;

        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.productCode'] = $variant ? $variant->getCode() : ($product ? $product->getCode() : 'UNKNOWN');
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.description'] = substr((string) $orderItem->getProductName(), 0, 26);
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.quantity'] = (string) $orderItem->getQuantity();
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.unitOfMeasure'] = 'PCS';
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.unitPrice'] = (string) $orderItem->getUnitPrice();
        $data['enhancedSchemeData.itemDetailLine' . $lineNumber . '.totalAmount'] = (string) $orderItem->getTotal();

        $commodityCode = $this->getCommodityCode($variant, $product);
        if ($commodityCode) {
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

    private function getCommodityCode(?ProductVariantInterface $variant, ?ProductInterface $product): ?string
    {
        if ($variant && method_exists($variant, 'getCommodityCode')) {
            $commodityCode = $variant->getCommodityCode();
            if ($commodityCode) {
                return substr($commodityCode, 0, 12);
            }
        }

        if ($product && method_exists($product, 'getCommodityCode')) {
            $commodityCode = $product->getCommodityCode();
            if ($commodityCode) {
                return substr($commodityCode, 0, 12);
            }
        }

        return null;
    }
}
