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
use Sylius\AdyenPlugin\Collector\ItemDetailLineCollectorInterface;
use Sylius\AdyenPlugin\Collector\Level3EsdCollector;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class Level3EsdCollectorTest extends TestCase
{
    private Level3EsdCollector $collector;

    private MockObject $level2Collector;

    private MockObject $itemDetailLineCollector;

    protected function setUp(): void
    {
        $this->level2Collector = $this->createMock(EsdCollectorInterface::class);
        $this->itemDetailLineCollector = $this->createMock(ItemDetailLineCollectorInterface::class);
        $this->collector = new Level3EsdCollector($this->level2Collector, $this->itemDetailLineCollector);
    }

    public function testItSupportsAllMerchantCategoryCodes(): void
    {
        $this->assertTrue($this->collector->supports('1234'));
        $this->assertTrue($this->collector->supports('5678'));
        $this->assertTrue($this->collector->supports('9999'));
    }

    public function testItCollectsLineItemData(): void
    {
        $orderItem = $this->createMock(OrderItemInterface::class);

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

        // Set up ItemDetailLineCollector expectations
        $this->itemDetailLineCollector->expects($this->once())
            ->method('collect')
            ->with($orderItem, 1)
            ->willReturn([
                'enhancedSchemeData.itemDetailLine1.productCode' => 'SKU-001',
                'enhancedSchemeData.itemDetailLine1.description' => 'Test Product Name',
                'enhancedSchemeData.itemDetailLine1.quantity' => '2',
                'enhancedSchemeData.itemDetailLine1.unitOfMeasure' => 'PCS',
                'enhancedSchemeData.itemDetailLine1.unitPrice' => '1000',
                'enhancedSchemeData.itemDetailLine1.totalAmount' => '2000',
            ]);

        $result = $this->collector->collect($order);

        $this->assertArrayHasKey('enhancedSchemeData.customerReference', $result);
        $this->assertArrayHasKey('enhancedSchemeData.totalTaxAmount', $result);
        $this->assertEquals('SKU-001', $result['enhancedSchemeData.itemDetailLine1.productCode']);
        $this->assertEquals('Test Product Name', $result['enhancedSchemeData.itemDetailLine1.description']);
        $this->assertEquals('2', $result['enhancedSchemeData.itemDetailLine1.quantity']);
        $this->assertEquals('PCS', $result['enhancedSchemeData.itemDetailLine1.unitOfMeasure']);
        $this->assertEquals('1000', $result['enhancedSchemeData.itemDetailLine1.unitPrice']);
        $this->assertEquals('2000', $result['enhancedSchemeData.itemDetailLine1.totalAmount']);
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.commodityCode', $result);
    }

    public function testItHandlesDiscountAmount(): void
    {
        $orderItem = $this->createMock(OrderItemInterface::class);

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

        // Set up ItemDetailLineCollector expectations
        $this->itemDetailLineCollector->expects($this->once())
            ->method('collect')
            ->with($orderItem, 1)
            ->willReturn([
                'enhancedSchemeData.itemDetailLine1.productCode' => 'SKU-002',
                'enhancedSchemeData.itemDetailLine1.description' => 'Discounted Product',
                'enhancedSchemeData.itemDetailLine1.quantity' => '1',
                'enhancedSchemeData.itemDetailLine1.unitOfMeasure' => 'PCS',
                'enhancedSchemeData.itemDetailLine1.unitPrice' => '1500',
                'enhancedSchemeData.itemDetailLine1.totalAmount' => '1200',
                'enhancedSchemeData.itemDetailLine1.discountAmount' => '300',
            ]);

        $result = $this->collector->collect($order);

        $this->assertEquals('300', $result['enhancedSchemeData.itemDetailLine1.discountAmount']);
    }

    public function testItHandlesMissingVariant(): void
    {
        $orderItem = $this->createMock(OrderItemInterface::class);

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

        // Set up ItemDetailLineCollector expectations
        $this->itemDetailLineCollector->expects($this->once())
            ->method('collect')
            ->with($orderItem, 1)
            ->willReturn([
                'enhancedSchemeData.itemDetailLine1.productCode' => 'UNKNOWN',
                'enhancedSchemeData.itemDetailLine1.description' => 'Product without variant',
                'enhancedSchemeData.itemDetailLine1.quantity' => '1',
                'enhancedSchemeData.itemDetailLine1.unitOfMeasure' => 'PCS',
                'enhancedSchemeData.itemDetailLine1.unitPrice' => '500',
                'enhancedSchemeData.itemDetailLine1.totalAmount' => '500',
            ]);

        $result = $this->collector->collect($order);

        $this->assertEquals('UNKNOWN', $result['enhancedSchemeData.itemDetailLine1.productCode']);
        $this->assertArrayNotHasKey('enhancedSchemeData.itemDetailLine1.commodityCode', $result);
    }
}
