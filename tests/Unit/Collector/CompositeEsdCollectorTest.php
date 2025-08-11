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
use Sylius\AdyenPlugin\Collector\CompositeEsdCollector;
use Sylius\AdyenPlugin\Collector\EsdCollectorInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class CompositeEsdCollectorTest extends TestCase
{
    public function testItShouldNotIncludeEsdWhenDisabled(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $collectors = [];
        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = ['esdEnabled' => false];

        $result = $composite->collect($order, $gatewayConfig);

        $this->assertSame([], $result);
    }

    public function testItShouldNotIncludeEsdForNonUsdCurrency(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('EUR');

        $collectors = [];
        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = ['esdEnabled' => true];

        $result = $composite->collect($order, $gatewayConfig);

        $this->assertSame([], $result);
    }

    public function testItShouldNotIncludeEsdForNonUsAddress(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('CA');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $collectors = [];
        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = ['esdEnabled' => true];

        $result = $composite->collect($order, $gatewayConfig);

        $this->assertSame([], $result);
    }

    public function testItShouldUseExplicitTypeCollector(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $expectedData = ['enhancedSchemeData.customerReference' => '123'];

        $airlineCollector = $this->createMock(EsdCollectorInterface::class);
        $airlineCollector->expects($this->once())
            ->method('collect')
            ->with($order)
            ->willReturn($expectedData);

        $level3Collector = $this->createMock(EsdCollectorInterface::class);
        $level3Collector->expects($this->never())
            ->method('collect');

        $collectors = [
            'airline' => $airlineCollector,
            'level3' => $level3Collector,
        ];

        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = [
            'esdEnabled' => true,
            'esdType' => 'airline',
        ];

        $result = $composite->collect($order, $gatewayConfig);

        $this->assertSame($expectedData, $result);
    }

    public function testItShouldAutoDetectCollectorByMcc(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $expectedData = ['enhancedSchemeData.lodgingData.checkInDate' => '2025-01-01'];

        $lodgingCollector = $this->createMock(EsdCollectorInterface::class);
        $lodgingCollector->expects($this->once())
            ->method('supports')
            ->with('7011')
            ->willReturn(true);
        $lodgingCollector->expects($this->once())
            ->method('collect')
            ->with($order)
            ->willReturn($expectedData);

        $level3Collector = $this->createMock(EsdCollectorInterface::class);
        $level3Collector->expects($this->any())
            ->method('supports')
            ->with('7011')
            ->willReturn(false);

        $collectors = [
            'lodging' => $lodgingCollector,
            'level3' => $level3Collector,
        ];

        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = [
            'esdEnabled' => true,
            'merchantCategoryCode' => '7011',
        ];

        $result = $composite->collect($order, $gatewayConfig);

        $this->assertSame($expectedData, $result);
    }

    public function testItShouldFallbackToLevel3(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $expectedData = ['enhancedSchemeData.totalTaxAmount' => 100];

        $level3Collector = $this->createMock(EsdCollectorInterface::class);
        $level3Collector->expects($this->once())
            ->method('collect')
            ->with($order)
            ->willReturn($expectedData);

        $collectors = [
            'level3' => $level3Collector,
        ];

        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = ['esdEnabled' => true];

        $result = $composite->collect($order, $gatewayConfig);

        $this->assertSame($expectedData, $result);
    }

    public function testItShouldFallbackToLevel2(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $expectedData = ['enhancedSchemeData.customerReference' => 'test'];

        $level2Collector = $this->createMock(EsdCollectorInterface::class);
        $level2Collector->expects($this->once())
            ->method('collect')
            ->with($order)
            ->willReturn($expectedData);

        $collectors = [
            'level2' => $level2Collector,
        ];

        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = ['esdEnabled' => true];

        $result = $composite->collect($order, $gatewayConfig);

        $this->assertSame($expectedData, $result);
    }

    public function testItShouldThrowExceptionWhenNoCollectorFound(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $collectors = [];
        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = ['esdEnabled' => true];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No ESD collector found');

        $composite->collect($order, $gatewayConfig);
    }

    public function testItShouldSupportCustomCurrencies(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('EUR');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $collectors = [];
        $composite = new CompositeEsdCollector($collectors, ['USD', 'EUR'], ['US']);

        $gatewayConfig = ['esdEnabled' => true];

        $this->assertTrue($composite->shouldIncludeEsd($order, $gatewayConfig));
    }

    public function testItShouldSupportCustomCountries(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('CA');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $collectors = [];
        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US', 'CA']);

        $gatewayConfig = ['esdEnabled' => true];

        $this->assertTrue($composite->shouldIncludeEsd($order, $gatewayConfig));
    }

    public function testItShouldRejectUnsupportedCurrencyAndCountryCombination(): void
    {
        $address = $this->createMock(AddressInterface::class);
        $address->expects($this->once())
            ->method('getCountryCode')
            ->willReturn('GB');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->once())
            ->method('getBillingAddress')
            ->willReturn($address);

        $collectors = [];
        $composite = new CompositeEsdCollector($collectors, ['USD'], ['US']);

        $gatewayConfig = ['esdEnabled' => true];

        $this->assertFalse($composite->shouldIncludeEsd($order, $gatewayConfig));
    }
}
