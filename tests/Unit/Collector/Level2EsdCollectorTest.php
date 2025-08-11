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
use Sylius\AdyenPlugin\Collector\Level2EsdCollector;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class Level2EsdCollectorTest extends TestCase
{
    private Level2EsdCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new Level2EsdCollector();
    }

    public function testItDoesNotSupportSpecificMerchantCategoryCodes(): void
    {
        $this->assertFalse($this->collector->supports('1234'));
        $this->assertFalse($this->collector->supports('5678'));
        $this->assertFalse($this->collector->supports('9999'));
    }

    public function testItCollectsBasicDataWithCustomer(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->once())->method('getId')->willReturn(123);

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())->method('getCustomer')->willReturn($customer);
        $order->expects($this->once())->method('getTaxTotal')->willReturn(500);

        $result = $this->collector->collect($order);

        $this->assertEquals([
            'enhancedSchemeData.customerReference' => '123',
            'enhancedSchemeData.totalTaxAmount' => 500,
        ], $result);
    }

    public function testItCollectsBasicDataWithoutCustomer(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())->method('getCustomer')->willReturn(null);
        $order->expects($this->once())->method('getId')->willReturn(456);
        $order->expects($this->once())->method('getTaxTotal')->willReturn(300);

        $result = $this->collector->collect($order);

        $this->assertEquals([
            'enhancedSchemeData.customerReference' => '456',
            'enhancedSchemeData.totalTaxAmount' => 300,
        ], $result);
    }
}
