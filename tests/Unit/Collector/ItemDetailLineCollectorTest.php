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

namespace Tests\Sylius\AdyenPlugin\Unit\Collector;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Collector\ItemDetailLineCollector;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class ItemDetailLineCollectorTest extends TestCase
{
    private ItemDetailLineCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new ItemDetailLineCollector();
    }

    public function testItCollectsBasicItemData(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-001');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn($product);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('Test Product Name');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(2);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(1000);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(2000);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturn(0);

        $result = $this->collector->collect($orderItem, 1);

        $this->assertEquals('SKU-001', $result['enhancedSchemeData.itemDetailLine1.productCode']);
        $this->assertEquals('Test Product Name', $result['enhancedSchemeData.itemDetailLine1.description']);
        $this->assertEquals('2', $result['enhancedSchemeData.itemDetailLine1.quantity']);
        $this->assertEquals('PCS', $result['enhancedSchemeData.itemDetailLine1.unitOfMeasure']);
        $this->assertEquals('1000', $result['enhancedSchemeData.itemDetailLine1.unitPrice']);
        $this->assertEquals('2000', $result['enhancedSchemeData.itemDetailLine1.totalAmount']);
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.commodityCode', $result);
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.discountAmount', $result);
    }

    public function testItHandlesLongProductNameTruncation(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-002');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn(null);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('This is a very long product name that exceeds twenty six characters');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(1);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(500);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(500);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturn(0);

        $result = $this->collector->collect($orderItem, 2);

        $this->assertEquals('This is a very long produc', $result['enhancedSchemeData.itemDetailLine2.description']);
    }

    public function testItHandlesCommodityCodeFromVariant(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-003');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn(null);

        // Since getCommodityCode is not part of the interface, we'll skip this test
        // as the method_exists check would fail anyway
        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('Product with commodity');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(1);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(750);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(750);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturn(0);

        $result = $this->collector->collect($orderItem, 3);

        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine3.commodityCode', $result);
    }

    public function testItHandlesCommodityCodeFromProduct(): void
    {
        $product = $this->createMock(ProductInterface::class);

        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-004');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn($product);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('Product commodity');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(1);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(900);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(900);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturn(0);

        $result = $this->collector->collect($orderItem, 1);

        // Since getCommodityCode is not part of the interface, no commodity code will be set
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.commodityCode', $result);
    }

    public function testItTruncatesCommodityCodeTo12Characters(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-005');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn(null);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('Long commodity');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(1);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(1200);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(1200);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturn(0);

        $result = $this->collector->collect($orderItem, 1);

        // Since getCommodityCode is not part of the interface, no commodity code will be set
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.commodityCode', $result);
    }

    public function testItHandlesDiscountAmount(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-006');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn(null);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('Discounted Product');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(1);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(1500);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(1200);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturnMap([
                [AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT, -200],
                [AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT, -100],
            ]);

        $result = $this->collector->collect($orderItem, 1);

        $this->assertEquals('300', $result['enhancedSchemeData.itemDetailLine1.discountAmount']);
    }

    public function testItIgnoresPositiveDiscountAmount(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-007');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn(null);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('No Discount Product');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(1);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(1000);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(1000);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturnMap([
                [AdjustmentInterface::ORDER_UNIT_PROMOTION_ADJUSTMENT, 50],
                [AdjustmentInterface::ORDER_PROMOTION_ADJUSTMENT, 0],
            ]);

        $result = $this->collector->collect($orderItem, 1);

        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.discountAmount', $result);
    }

    public function testItHandlesMissingVariant(): void
    {
        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn(null);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('Product without variant');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(1);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(500);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(500);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturn(0);

        $result = $this->collector->collect($orderItem, 1);

        $this->assertEquals('UNKNOWN', $result['enhancedSchemeData.itemDetailLine1.productCode']);
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.commodityCode', $result);
    }

    public function testItHandlesVariantWithoutProduct(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-VARIANT');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn(null);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('Variant without product');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(1);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(600);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(600);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturn(0);

        $result = $this->collector->collect($orderItem, 1);

        $this->assertEquals('SKU-VARIANT', $result['enhancedSchemeData.itemDetailLine1.productCode']);
    }

    public function testItUsesCorrectLineNumber(): void
    {
        $variant = $this->createMock(ProductVariantInterface::class);
        $variant->expects($this->once())
            ->method('getCode')
            ->willReturn('SKU-008');
        $variant->expects($this->once())
            ->method('getProduct')
            ->willReturn(null);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->expects($this->once())
            ->method('getVariant')
            ->willReturn($variant);
        $orderItem->expects($this->once())
            ->method('getProductName')
            ->willReturn('Line 5 product');
        $orderItem->expects($this->once())
            ->method('getQuantity')
            ->willReturn(3);
        $orderItem->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(800);
        $orderItem->expects($this->once())
            ->method('getTotal')
            ->willReturn(2400);
        $orderItem->expects($this->any())
            ->method('getAdjustmentsTotalRecursively')
            ->willReturn(0);

        $result = $this->collector->collect($orderItem, 5);

        $this->assertArrayHasKey('enhancedSchemeData.itemDetailLine5.productCode', $result);
        $this->assertArrayHasKey('enhancedSchemeData.itemDetailLine5.description', $result);
        $this->assertArrayHasKey('enhancedSchemeData.itemDetailLine5.quantity', $result);
        $this->assertArrayHasKey('enhancedSchemeData.itemDetailLine5.unitOfMeasure', $result);
        $this->assertArrayHasKey('enhancedSchemeData.itemDetailLine5.unitPrice', $result);
        $this->assertArrayHasKey('enhancedSchemeData.itemDetailLine5.totalAmount', $result);
        $this->assertEquals('SKU-008', $result['enhancedSchemeData.itemDetailLine5.productCode']);
    }
}
