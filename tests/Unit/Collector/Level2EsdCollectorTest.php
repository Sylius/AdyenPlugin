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
use Sylius\Component\Core\Model\AddressInterface;
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
        // Level 2 is a fallback and doesn't support specific MCCs
        // The composite collector will use it as a fallback
        $this->assertFalse($this->collector->supports('1234'));
        $this->assertFalse($this->collector->supports('5678'));
        $this->assertFalse($this->collector->supports('9999'));
    }

    public function testItCollectsBasicDataWithCustomer(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->once())
            ->method('getId')
            ->willReturn(123);

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);
        $order->expects($this->once())
            ->method('getTaxTotal')
            ->willReturn(500);
        $order->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn(null);

        $result = $this->collector->collect($order);

        $this->assertEquals([
            'enhancedSchemeData.customerReference' => '123',
            'enhancedSchemeData.totalTaxAmount' => 500,
        ], $result);
    }

    public function testItCollectsBasicDataWithoutCustomer(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCustomer')
            ->willReturn(null);
        $order->expects($this->once())
            ->method('getId')
            ->willReturn(456);
        $order->expects($this->once())
            ->method('getTaxTotal')
            ->willReturn(300);
        $order->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn(null);

        $result = $this->collector->collect($order);

        $this->assertEquals([
            'enhancedSchemeData.customerReference' => '456',
            'enhancedSchemeData.totalTaxAmount' => 300,
        ], $result);
    }

    public function testItCollectsShippingAddressData(): void
    {
        $shippingAddress = $this->createMock(AddressInterface::class);
        $shippingAddress->expects($this->once())
            ->method('getPostcode')
            ->willReturn('10001');
        $shippingAddress->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('US');
        $shippingAddress->expects($this->exactly(2))
            ->method('getProvinceCode')
            ->willReturn('NY');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->once())
            ->method('getId')
            ->willReturn(789);

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);
        $order->expects($this->once())
            ->method('getTaxTotal')
            ->willReturn(750);
        $order->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $result = $this->collector->collect($order);

        $this->assertEquals([
            'enhancedSchemeData.customerReference' => '789',
            'enhancedSchemeData.totalTaxAmount' => 750,
            'enhancedSchemeData.destinationPostalCode' => '10001',
            'enhancedSchemeData.destinationCountryCode' => 'US',
            'enhancedSchemeData.destinationStateProvinceCode' => 'NY',
        ], $result);
    }

    public function testItCollectsShippingAddressWithoutProvinceCode(): void
    {
        $shippingAddress = $this->createMock(AddressInterface::class);
        $shippingAddress->expects($this->once())
            ->method('getPostcode')
            ->willReturn('SW1A 1AA');
        $shippingAddress->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('GB');
        $shippingAddress->expects($this->once())
            ->method('getProvinceCode')
            ->willReturn(null);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->once())
            ->method('getId')
            ->willReturn(101);

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCustomer')
            ->willReturn($customer);
        $order->expects($this->once())
            ->method('getTaxTotal')
            ->willReturn(0);
        $order->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn($shippingAddress);

        $result = $this->collector->collect($order);

        $this->assertEquals([
            'enhancedSchemeData.customerReference' => '101',
            'enhancedSchemeData.totalTaxAmount' => 0,
            'enhancedSchemeData.destinationPostalCode' => 'SW1A 1AA',
            'enhancedSchemeData.destinationCountryCode' => 'GB',
        ], $result);
    }
}
