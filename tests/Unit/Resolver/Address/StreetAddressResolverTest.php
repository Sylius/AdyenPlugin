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

namespace Tests\Sylius\AdyenPlugin\Unit\Resolver\Address;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Resolver\Address\StreetAddressResolver;
use Sylius\AdyenPlugin\Resolver\Address\StreetAddressResolverInterface;

final class StreetAddressResolverTest extends TestCase
{
    private const UNKNOWN_HOUSE_NUMBER_OR_NAME = 'N/A';

    private const EXAMPLE_STREET_ADDRESS = 'Paleisstraat';

    /** @var StreetAddressResolverInterface */
    private $streetAddressResolver;

    protected function setUp(): void
    {
        $this->streetAddressResolver = new StreetAddressResolver();
    }

    #[DataProvider('provideHouseNumberFirst')]
    public function testResolveHouseNumberFirst(
        string $streetAddress,
        string $street,
        string $houseNumber,
    ): void {
        $model = $this->streetAddressResolver->resolve($streetAddress);

        self::assertEquals($street, $model->getStreet());
        self::assertEquals($houseNumber, $model->getHouseNumber());
    }

    #[DataProvider('provideHouseNumberLast')]
    public function testResolveHouseNumberLast(
        string $streetAddress,
        string $street,
        string $houseNumber,
    ): void {
        $model = $this->streetAddressResolver->resolve($streetAddress);

        self::assertEquals($street, $model->getStreet());
        self::assertEquals($houseNumber, $model->getHouseNumber());
    }

    public function testEmptyStreetAddress(): void
    {
        $model = $this->streetAddressResolver->resolve('');

        self::assertEquals('', $model->getStreet());
        self::assertEquals(self::UNKNOWN_HOUSE_NUMBER_OR_NAME, $model->getHouseNumber());
    }

    public function testEmptyHouseNumberOrName(): void
    {
        $model = $this->streetAddressResolver->resolve(self::EXAMPLE_STREET_ADDRESS);

        self::assertEquals(self::EXAMPLE_STREET_ADDRESS, $model->getStreet());
        self::assertEquals(self::UNKNOWN_HOUSE_NUMBER_OR_NAME, $model->getHouseNumber());
    }

    public static function provideHouseNumberLast(): array
    {
        return [
            ['Zamojska 1', 'Zamojska', '1'],
            ['Morska 28d', 'Morska', '28d'],
            ['ul. Parkowa 1d', 'ul. Parkowa', '1d'],
            ['Akacjowa 98 z', 'Akacjowa', '98 z'],
            ['ul. Akacjowa 76 b', 'ul. Akacjowa', '76 b'],
            ['Krakowska 1/2', 'Krakowska', '1/2'],
            ['Krakowska 1d/2', 'Krakowska', '1d/2'],
        ];
    }

    public static function provideHouseNumberFirst(): array
    {
        return [
            ['1 Montfortanenlaan', 'Montfortanenlaan', '1'],
            ['2D Gasthuislaan', 'Gasthuislaan', '2D'],
            ['98 W Molstraat', 'Molstraat', '98 W'],
            ['76B/2 ul. Akacjowa', 'ul. Akacjowa', '76B/2'],
        ];
    }
}
