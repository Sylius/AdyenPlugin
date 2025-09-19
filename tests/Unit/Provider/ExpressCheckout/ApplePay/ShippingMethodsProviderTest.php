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

namespace Tests\Sylius\AdyenPlugin\Unit\Provider\ExpressCheckout\ApplePay;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Exception\NoShippingMethodsAvailableException;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\ShippingMethodsProvider;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

final class ShippingMethodsProviderTest extends TestCase
{
    private MockObject&ShippingMethodsResolverInterface $shippingMethodsResolver;

    private MockObject&ServiceRegistryInterface $calculators;

    private ShippingMethodsProvider $shippingMethodsProvider;

    protected function setUp(): void
    {
        $this->shippingMethodsResolver = $this->createMock(ShippingMethodsResolverInterface::class);
        $this->calculators = $this->createMock(ServiceRegistryInterface::class);

        $this->shippingMethodsProvider = new ShippingMethodsProvider($this->shippingMethodsResolver, $this->calculators);
    }

    public function testProvideReturnsEmptyArrayWhenShippingIsNotRequired(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('isShippingRequired')->willReturn(false);

        $result = $this->shippingMethodsProvider->provide($order);

        $this->assertSame([], $result);
    }

    public function testProvideThrowsExceptionWhenNoShippingMethodsAvailable(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);

        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        $this->shippingMethodsResolver->method('getSupportedMethods')->with($shipment)->willReturn([]);

        $this->expectException(NoShippingMethodsAvailableException::class);

        $this->shippingMethodsProvider->provide($order);
    }

    public function testProvideReturnsFormattedShippingMethods(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $shipment = $this->createMock(ShipmentInterface::class);

        $shippingMethod1 = $this->createMock(ShippingMethodInterface::class);
        $shippingMethod1->method('getCode')->willReturn('standard');
        $shippingMethod1->method('getName')->willReturn('Standard Shipping');
        $shippingMethod1->method('getDescription')->willReturn('5-7 business days');
        $shippingMethod1->method('getCalculator')->willReturn('flat_rate');
        $shippingMethod1->method('getConfiguration')->willReturn(['amount' => 500]);

        $shippingMethod2 = $this->createMock(ShippingMethodInterface::class);
        $shippingMethod2->method('getCode')->willReturn('express');
        $shippingMethod2->method('getName')->willReturn('Express Shipping');
        $shippingMethod2->method('getDescription')->willReturn(null);
        $shippingMethod2->method('getCalculator')->willReturn('flat_rate');
        $shippingMethod2->method('getConfiguration')->willReturn(['amount' => 1500]);

        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getShipments')->willReturn(new ArrayCollection([$shipment]));

        $this->shippingMethodsResolver
            ->method('getSupportedMethods')
            ->with($shipment)
            ->willReturn([$shippingMethod1, $shippingMethod2])
        ;

        $calculator = $this->createMock(CalculatorInterface::class);
        $calculator->method('calculate')->willReturnOnConsecutiveCalls(999, 2499);

        $this->calculators->method('get')->with('flat_rate')->willReturn($calculator);

        $result = $this->shippingMethodsProvider->provide($order);

        $expected = [
            'newShippingMethods' => [
                [
                    'identifier' => 'standard',
                    'label' => 'Standard Shipping',
                    'amount' => '9.99',
                    'detail' => '5-7 business days',
                ],
                [
                    'identifier' => 'express',
                    'label' => 'Express Shipping',
                    'amount' => '24.99',
                    'detail' => '',
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }
}
