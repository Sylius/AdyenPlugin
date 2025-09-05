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

namespace Tests\Sylius\AdyenPlugin\Unit\Mapper;

use Adyen\Model\Checkout\PaymentMethod as AdyenPaymentMethod;
use Adyen\Model\Checkout\StoredPaymentMethod as AdyenStoredPaymentMethod;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Mapper\AdyenPaymentMethodsMapper;
use Sylius\AdyenPlugin\Model\PaymentMethod;
use Sylius\AdyenPlugin\Model\StoredPaymentMethod;

final class AdyenPaymentMethodsMapperTest extends TestCase
{
    private AdyenPaymentMethodsMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AdyenPaymentMethodsMapper();
    }

    public function testMapAvailableMapsValidMethods(): void
    {
        $pm1 = $this->mockPaymentMethod(
            type: 'scheme',
            name: 'Credit Card',
            brands: ['visa', 'mc'],
            configuration: ['supported' => true],
        );

        $pm2 = $this->mockPaymentMethod(
            type: 'ideal',
            name: 'iDEAL',
            issuers: [['id' => 'ING', 'name' => 'ING']],
        );

        $result = $this->mapper->mapAvailable([$pm1, $pm2]);

        $expected = [
            new PaymentMethod(
                type: 'scheme',
                name: 'Credit Card',
                brands: ['visa', 'mc'],
                configuration: ['supported' => true],
            ),
            new PaymentMethod(
                type: 'ideal',
                name: 'iDEAL',
                configuration: null,
                issuers: [['id' => 'ING', 'name' => 'ING']],
            ),
        ];

        self::assertEquals($expected, $result);
    }

    public function testMapAvailableSkipsMethodsWithEmptyOrNullType(): void
    {
        $valid = $this->mockPaymentMethod(type: 'paypal', name: 'PayPal');
        $emptyType = $this->mockPaymentMethod(type: '', name: 'X');
        $nullType = $this->mockPaymentMethod(type: null, name: 'Y');

        $result = $this->mapper->mapAvailable([$valid, $emptyType, $nullType]);

        self::assertCount(1, $result);
        self::assertEquals(
            [new PaymentMethod(type: 'paypal', name: 'PayPal', brands: [], configuration: null, issuers: null)],
            $result,
        );
    }

    public function testMapStoredMapsValidMethods(): void
    {
        $sm1 = $this->mockStoredPaymentMethod(
            id: 'abc123',
            type: 'scheme',
            brand: 'visa',
            lastFour: '4242',
            expiryMonth: '12',
            expiryYear: '2099',
            holderName: 'John Doe',
            supportedShopperInteractions: ['Ecommerce'],
        );

        $sm2 = $this->mockStoredPaymentMethod(
            id: 'def456',
            type: 'scheme',
            brand: 'mc',
            lastFour: '1111',
        );

        $result = $this->mapper->mapStored([$sm1, $sm2]);

        $expected = [
            new StoredPaymentMethod(
                id: 'abc123',
                type: 'scheme',
                supportedShopperInteractions: ['Ecommerce'],
                brand: 'visa',
                lastFour: '4242',
                expiryMonth: '12',
                expiryYear: '2099',
                holderName: 'John Doe',
            ),
            new StoredPaymentMethod(
                id: 'def456',
                type: 'scheme',
                supportedShopperInteractions: [],
                brand: 'mc',
                lastFour: '1111',
                expiryMonth: null,
                expiryYear: null,
                holderName: null,
            ),
        ];

        self::assertEquals($expected, $result);
    }

    public function testMapStoredSkipsWhenIdOrTypeIsNull(): void
    {
        $valid = $this->mockStoredPaymentMethod(id: 'ok', type: 'scheme');
        $nullId = $this->mockStoredPaymentMethod(id: null, type: 'scheme');
        $nullType = $this->mockStoredPaymentMethod(id: 'x', type: null);

        $result = $this->mapper->mapStored([$valid, $nullId, $nullType]);

        self::assertCount(1, $result);
        self::assertEquals(
            [new StoredPaymentMethod(id: 'ok', type: 'scheme', supportedShopperInteractions: [], brand: null, lastFour: null, expiryMonth: null, expiryYear: null, holderName: null)],
            $result,
        );
    }

    private function mockPaymentMethod(
        ?string $type,
        ?string $name = null,
        ?array $brands = null,
        ?array $configuration = null,
        ?array $issuers = null,
    ): AdyenPaymentMethod {
        $mock = $this->createMock(AdyenPaymentMethod::class);
        $mock->method('getType')->willReturn($type);
        $mock->method('getName')->willReturn($name);
        $mock->method('getBrands')->willReturn($brands);
        $mock->method('getConfiguration')->willReturn($configuration);
        $mock->method('getIssuers')->willReturn($issuers);

        return $mock;
    }

    private function mockStoredPaymentMethod(
        ?string $id,
        ?string $type,
        ?string $brand = null,
        ?string $lastFour = null,
        ?string $expiryMonth = null,
        ?string $expiryYear = null,
        ?string $holderName = null,
        ?array $supportedShopperInteractions = null,
    ): AdyenStoredPaymentMethod {
        $mock = $this->createMock(AdyenStoredPaymentMethod::class);
        $mock->method('getId')->willReturn($id);
        $mock->method('getType')->willReturn($type);
        $mock->method('getBrand')->willReturn($brand);
        $mock->method('getLastFour')->willReturn($lastFour);
        $mock->method('getExpiryMonth')->willReturn($expiryMonth);
        $mock->method('getExpiryYear')->willReturn($expiryYear);
        $mock->method('getHolderName')->willReturn($holderName);
        $mock->method('getSupportedShopperInteractions')->willReturn($supportedShopperInteractions);

        return $mock;
    }
}
