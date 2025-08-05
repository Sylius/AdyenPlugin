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

namespace Sylius\AdyenPlugin\Normalizer;

use Sylius\AdyenPlugin\Client\ClientPayloadFactoryInterface;
use Sylius\AdyenPlugin\Resolver\Address\StreetAddressResolverInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Webmozart\Assert\Assert;

final class AddressNormalizer extends AbstractPaymentNormalizer
{
    /** @var StreetAddressResolverInterface */
    private $streetAddressResolver;

    public function __construct(StreetAddressResolverInterface $streetAddressResolver)
    {
        $this->streetAddressResolver = $streetAddressResolver;
    }

    /**
     * @param mixed|AddressInterface $data
     */
    public function supportsNormalization(
        $data,
        ?string $format = null,
        array $context = [],
    ): bool {
        return parent::supportsNormalization($data, $format, $context) && $data instanceof AddressInterface;
    }

    /**
     * @param mixed $object
     */
    public function normalize(
        $object,
        ?string $format = null,
        array $context = [],
    ): array {
        Assert::isInstanceOf($object, AddressInterface::class);

        $address = [
            'postalCode' => (string) $object->getPostcode(),
            'city' => (string) $object->getCity(),
            'country' => $object->getCountryCode() ?? ClientPayloadFactoryInterface::NO_COUNTRY_AVAILABLE_PLACEHOLDER,
            'stateOrProvince' => (string) $object->getProvinceName(),
        ];

        return \array_merge(
            $address,
            $this->getStreetAddressPayload((string) $object->getStreet()),
        );
    }

    private function getStreetAddressPayload(string $street): array
    {
        $streetAddress = $this->streetAddressResolver->resolve($street);

        return [
            'street' => $streetAddress->getStreet(),
            'houseNumberOrName' => $streetAddress->getHouseNumber(),
        ];
    }
}
