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

namespace Tests\Sylius\AdyenPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Collector\EsdCollectorInterface;
use Sylius\AdyenPlugin\Provider\EsdTypeProvider;

final class EsdTypeProviderTest extends TestCase
{
    public function testItReturnsEmptyArrayWhenNoCollectors(): void
    {
        $provider = new EsdTypeProvider([]);

        $types = $provider->getAvailableTypes();

        $this->assertIsArray($types);
        $this->assertEmpty($types);
    }

    public function testItReturnsTypesFromCollectors(): void
    {
        $level2Collector = $this->createMock(EsdCollectorInterface::class);
        $level3Collector = $this->createMock(EsdCollectorInterface::class);

        $collectors = [
            'level2' => $level2Collector,
            'level3' => $level3Collector,
        ];

        $provider = new EsdTypeProvider($collectors);

        $types = $provider->getAvailableTypes();

        $this->assertIsArray($types);
        $this->assertCount(2, $types);
        $this->assertArrayHasKey('sylius_adyen.ui.esd_type_level2', $types);
        $this->assertArrayHasKey('sylius_adyen.ui.esd_type_level3', $types);
        $this->assertEquals('level2', $types['sylius_adyen.ui.esd_type_level2']);
        $this->assertEquals('level3', $types['sylius_adyen.ui.esd_type_level3']);
    }

    public function testItHandlesIterableCollectors(): void
    {
        $level2Collector = $this->createMock(EsdCollectorInterface::class);
        $airlineCollector = $this->createMock(EsdCollectorInterface::class);
        $lodgingCollector = $this->createMock(EsdCollectorInterface::class);

        $collectors = new \ArrayIterator([
            'level2' => $level2Collector,
            'airline' => $airlineCollector,
            'lodging' => $lodgingCollector,
        ]);

        $provider = new EsdTypeProvider($collectors);

        $types = $provider->getAvailableTypes();

        $this->assertIsArray($types);
        $this->assertCount(3, $types);
        $this->assertArrayHasKey('sylius_adyen.ui.esd_type_level2', $types);
        $this->assertArrayHasKey('sylius_adyen.ui.esd_type_airline', $types);
        $this->assertArrayHasKey('sylius_adyen.ui.esd_type_lodging', $types);
        $this->assertEquals('level2', $types['sylius_adyen.ui.esd_type_level2']);
        $this->assertEquals('airline', $types['sylius_adyen.ui.esd_type_airline']);
        $this->assertEquals('lodging', $types['sylius_adyen.ui.esd_type_lodging']);
    }

    public function testItGeneratesCorrectLabelsForTypes(): void
    {
        $collector = $this->createMock(EsdCollectorInterface::class);

        $collectors = [
            'custom_type' => $collector,
            'another_type' => $collector,
            'special-type' => $collector,
        ];

        $provider = new EsdTypeProvider($collectors);

        $types = $provider->getAvailableTypes();

        $this->assertArrayHasKey('sylius_adyen.ui.esd_type_custom_type', $types);
        $this->assertArrayHasKey('sylius_adyen.ui.esd_type_another_type', $types);
        $this->assertArrayHasKey('sylius_adyen.ui.esd_type_special-type', $types);
        $this->assertEquals('custom_type', $types['sylius_adyen.ui.esd_type_custom_type']);
        $this->assertEquals('another_type', $types['sylius_adyen.ui.esd_type_another_type']);
        $this->assertEquals('special-type', $types['sylius_adyen.ui.esd_type_special-type']);
    }

    public function testItPreservesOrderOfCollectors(): void
    {
        $collectors = [
            'zebra' => $this->createMock(EsdCollectorInterface::class),
            'alpha' => $this->createMock(EsdCollectorInterface::class),
            'beta' => $this->createMock(EsdCollectorInterface::class),
        ];

        $provider = new EsdTypeProvider($collectors);

        $types = $provider->getAvailableTypes();
        $keys = array_keys($types);

        $this->assertEquals('sylius_adyen.ui.esd_type_zebra', $keys[0]);
        $this->assertEquals('sylius_adyen.ui.esd_type_alpha', $keys[1]);
        $this->assertEquals('sylius_adyen.ui.esd_type_beta', $keys[2]);
    }
}
