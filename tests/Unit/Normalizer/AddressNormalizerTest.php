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

namespace Tests\Sylius\AdyenPlugin\Unit\Normalizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Client\ClientPayloadFactoryInterface;
use Sylius\AdyenPlugin\Model\StreetAddressModel;
use Sylius\AdyenPlugin\Normalizer\AbstractPaymentNormalizer;
use Sylius\AdyenPlugin\Normalizer\AddressNormalizer;
use Sylius\AdyenPlugin\Resolver\Address\StreetAddressResolverInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\AddressInterface;
use Tests\Sylius\AdyenPlugin\Unit\AddressMother;

class AddressNormalizerTest extends TestCase
{
    /** @var AddressNormalizer */
    private $normalizer;

    /** @var StreetAddressResolverInterface */
    private $streetAddressResolver;

    protected function setUp(): void
    {
        $this->streetAddressResolver = $this->createMock(StreetAddressResolverInterface::class);
        $this->streetAddressResolver
            ->method('resolve')
            ->with(AddressMother::BILLING_STREET)
            ->willReturn(
                new StreetAddressModel(
                    AddressMother::BILLING_STREET_NAME_ONLY,
                    AddressMother::BILLING_HOUSE_NAME_OR_NUMBER,
                ),
            );

        $this->normalizer = new AddressNormalizer($this->streetAddressResolver);
    }

    public static function provideForSupportsNormalization(): array
    {
        return [
            'without context and address' => [[], null, false],
            'without context' => [[], new Address(), false],
            'with context and address' => [[AbstractPaymentNormalizer::NORMALIZER_ENABLED => 1], new Address(), true],
        ];
    }

    #[DataProvider('provideForSupportsNormalization')]
    public function testSupportsNormalization(
        array $context,
        ?AddressInterface $order,
        bool $pass,
    ): void {
        $this->assertEquals($pass, $this->normalizer->supportsNormalization($order, null, $context));
    }

    public static function provideForTestNormalize(): array
    {
        $address = AddressMother::createBillingAddress();
        $addressWithoutCode = clone $address;
        $addressWithoutCode->setCountryCode(null);

        return [
            'without country code' => [
                $addressWithoutCode,
                ClientPayloadFactoryInterface::NO_COUNTRY_AVAILABLE_PLACEHOLDER,
            ],
            'with country code' => [
                $address,
                $address->getCountryCode(),
            ],
        ];
    }

    #[DataProvider('provideForTestNormalize')]
    public function testNormalize(AddressInterface $address, string $expectedCountryCode): void
    {
        $result = $this->normalizer->normalize($address);

        $this->assertEquals([
            'street' => AddressMother::BILLING_STREET_NAME_ONLY,
            'postalCode' => AddressMother::BILLING_POSTCODE,
            'city' => AddressMother::BILLING_CITY,
            'country' => $expectedCountryCode,
            'stateOrProvince' => AddressMother::BILLING_PROVINCE,
            'houseNumberOrName' => AddressMother::BILLING_HOUSE_NAME_OR_NUMBER,
        ], $result);
    }
}
