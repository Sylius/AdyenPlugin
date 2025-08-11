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

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Collector\EsdCollectorInterface;
use Sylius\AdyenPlugin\Collector\Level3EsdCollector;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class Level3EsdCollectorTest extends TestCase
{
    private Level3EsdCollector $collector;

    private MockObject $level2Collector;

    protected function setUp(): void
    {
        $this->level2Collector = $this->createMock(EsdCollectorInterface::class);
        $this->collector = new Level3EsdCollector($this->level2Collector);
    }

    public function testItSupportsAllMerchantCategoryCodes(): void
    {
        $this->assertTrue($this->collector->supports('1234'));
        $this->assertTrue($this->collector->supports('5678'));
        $this->assertTrue($this->collector->supports('9999'));
    }

    public function testItCollectsLineItemData(): void
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

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getItems')
            ->willReturn(new ArrayCollection([$orderItem]));
        $order->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime('2023-01-01'));
        $order->expects($this->once())
            ->method('getShippingTotal')
            ->willReturn(500);
        $order->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn(null);

        // Set up Level2 collector expectations
        $this->level2Collector->expects($this->once())
            ->method('collect')
            ->with($order)
            ->willReturn([
                'enhancedSchemeData.customerReference' => '1',
                'enhancedSchemeData.totalTaxAmount' => 100,
            ]);

        $result = $this->collector->collect($order);

        $this->assertArrayHasKey('enhancedSchemeData.customerReference', $result);
        $this->assertArrayHasKey('enhancedSchemeData.totalTaxAmount', $result);
        $this->assertEquals('SKU-001', $result['enhancedSchemeData.itemDetailLine1.productCode']);
        $this->assertEquals('Test Product Name', $result['enhancedSchemeData.itemDetailLine1.description']);
        $this->assertEquals(2, $result['enhancedSchemeData.itemDetailLine1.quantity']);
        $this->assertEquals('PCS', $result['enhancedSchemeData.itemDetailLine1.unitOfMeasure']);
        $this->assertEquals(1000, $result['enhancedSchemeData.itemDetailLine1.unitPrice']);
        $this->assertEquals(2000, $result['enhancedSchemeData.itemDetailLine1.totalAmount']);
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.commodityCode', $result);
    }

    public function testItHandlesDiscountAmount(): void
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
                ['order_unit_promotion', -200],
                ['order_promotion', -100],
            ]);

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getItems')
            ->willReturn(new ArrayCollection([$orderItem]));
        $order->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime('2023-01-01'));
        $order->expects($this->once())
            ->method('getShippingTotal')
            ->willReturn(500);
        $order->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn(null);

        // Set up Level2 collector expectations
        $this->level2Collector->expects($this->once())
            ->method('collect')
            ->with($order)
            ->willReturn([
                'enhancedSchemeData.customerReference' => '2',
                'enhancedSchemeData.totalTaxAmount' => 0,
            ]);

        $result = $this->collector->collect($order);

        $this->assertEquals(300, $result['enhancedSchemeData.itemDetailLine1.discountAmount']);
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

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getItems')
            ->willReturn(new ArrayCollection([$orderItem]));
        $order->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime('2023-01-01'));
        $order->expects($this->once())
            ->method('getShippingTotal')
            ->willReturn(500);
        $order->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn(null);

        // Set up Level2 collector expectations
        $this->level2Collector->expects($this->once())
            ->method('collect')
            ->with($order)
            ->willReturn([
                'enhancedSchemeData.customerReference' => '4',
                'enhancedSchemeData.totalTaxAmount' => 0,
            ]);

        $result = $this->collector->collect($order);

        $this->assertEquals('UNKNOWN', $result['enhancedSchemeData.itemDetailLine1.productCode']);
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.commodityCode', $result);
    }
}
