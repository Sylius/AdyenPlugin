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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker\Eligibility;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\Eligibility\ZoneShippingMethodEligibilityChecker;
use Sylius\Component\Addressing\Matcher\ZoneMatcherInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Scope;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;

final class ZoneShippingMethodEligibilityCheckerTest extends TestCase
{
    private ZoneShippingMethodEligibilityChecker $checker;

    private ZoneMatcherInterface $zoneMatcher;

    protected function setUp(): void
    {
        $this->zoneMatcher = $this->createMock(ZoneMatcherInterface::class);
        $this->checker = new ZoneShippingMethodEligibilityChecker($this->zoneMatcher);
    }

    public function testReturnsTrueWhenNoShippingAddress(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);

        $shipment->method('getOrder')->willReturn($order);
        $order->method('getShippingAddress')->willReturn(null);

        $result = $this->checker->isEligible($shipment, $shippingMethod);

        $this->assertTrue($result);
    }

    public function testReturnsTrueWhenZoneMatches(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);
        $zone = $this->createMock(ZoneInterface::class);
        $shippingMethodZone = $this->createMock(ZoneInterface::class);

        $shipment->method('getOrder')->willReturn($order);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $shippingMethod->method('getZone')->willReturn($shippingMethodZone);

        $zone->method('getCode')->willReturn('EU');
        $shippingMethodZone->method('getCode')->willReturn('EU');

        $this->zoneMatcher
            ->method('matchAll')
            ->with($shippingAddress, Scope::SHIPPING)
            ->willReturn([$zone]);

        $result = $this->checker->isEligible($shipment, $shippingMethod);

        $this->assertTrue($result);
    }

    public function testReturnsFalseWhenNoZoneMatches(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);
        $zone = $this->createMock(ZoneInterface::class);
        $shippingMethodZone = $this->createMock(ZoneInterface::class);

        $shipment->method('getOrder')->willReturn($order);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $shippingMethod->method('getZone')->willReturn($shippingMethodZone);

        $zone->method('getCode')->willReturn('US');
        $shippingMethodZone->method('getCode')->willReturn('EU');

        $this->zoneMatcher
            ->method('matchAll')
            ->with($shippingAddress, Scope::SHIPPING)
            ->willReturn([$zone]);

        $result = $this->checker->isEligible($shipment, $shippingMethod);

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenNoZonesFound(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);
        $shippingMethodZone = $this->createMock(ZoneInterface::class);

        $shipment->method('getOrder')->willReturn($order);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $shippingMethod->method('getZone')->willReturn($shippingMethodZone);

        $this->zoneMatcher
            ->method('matchAll')
            ->with($shippingAddress, Scope::SHIPPING)
            ->willReturn([]);

        $result = $this->checker->isEligible($shipment, $shippingMethod);

        $this->assertFalse($result);
    }

    public function testHandlesMultipleZones(): void
    {
        $shipment = $this->createMock(ShipmentInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $shippingMethod = $this->createMock(ShippingMethodInterface::class);

        $zone1 = $this->createMock(ZoneInterface::class);
        $zone2 = $this->createMock(ZoneInterface::class);
        $zone3 = $this->createMock(ZoneInterface::class);
        $shippingMethodZone = $this->createMock(ZoneInterface::class);

        $shipment->method('getOrder')->willReturn($order);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $shippingMethod->method('getZone')->willReturn($shippingMethodZone);

        $zone1->method('getCode')->willReturn('US');
        $zone2->method('getCode')->willReturn('EU');
        $zone3->method('getCode')->willReturn('ASIA');
        $shippingMethodZone->method('getCode')->willReturn('EU');

        $this->zoneMatcher
            ->method('matchAll')
            ->with($shippingAddress, Scope::SHIPPING)
            ->willReturn([$zone1, $zone2, $zone3]);

        $result = $this->checker->isEligible($shipment, $shippingMethod);

        $this->assertTrue($result);
    }
}
