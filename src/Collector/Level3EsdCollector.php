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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class Level3EsdCollector implements EsdCollectorInterface
{
    private EsdCollectorInterface $level2Collector;

    public function __construct(EsdCollectorInterface $level2Collector)
    {
        $this->level2Collector = $level2Collector;
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
            $itemNumber = $index + 1;
            $variant = $item->getVariant();
            $product = $variant ? $variant->getProduct() : null;

            $data['enhancedSchemeData.itemDetailLine' . $itemNumber . '.productCode'] = $variant ? $variant->getCode() : ($product ? $product->getCode() : 'UNKNOWN');
            $data['enhancedSchemeData.itemDetailLine' . $itemNumber . '.description'] = substr((string) $item->getProductName(), 0, 26);
            $data['enhancedSchemeData.itemDetailLine' . $itemNumber . '.quantity'] = (string) $item->getQuantity();
            $data['enhancedSchemeData.itemDetailLine' . $itemNumber . '.unitOfMeasure'] = 'PCS';
            $data['enhancedSchemeData.itemDetailLine' . $itemNumber . '.unitPrice'] = (string) $item->getUnitPrice();
            $data['enhancedSchemeData.itemDetailLine' . $itemNumber . '.totalAmount'] = (string) $item->getTotal();

            $commodityCode = $this->getCommodityCode($variant, $product);
            if ($commodityCode) {
                $data['enhancedSchemeData.itemDetailLine' . $itemNumber . '.commodityCode'] = $commodityCode;
            }

            $discountAmount =
                $item->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT) +
                $item->getAdjustmentsTotalRecursively(AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT)
            ;
            if ($discountAmount < 0) {
                $data['enhancedSchemeData.itemDetailLine' . $itemNumber . '.discountAmount'] = (string) abs($discountAmount);
            }
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
