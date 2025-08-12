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

namespace Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay;

use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;

class AddressProvider implements AddressProviderInterface
{
    public function __construct(
        private readonly AddressFactoryInterface $addressFactory,
    ) {
    }

    public function createTemporaryAddress(array $addressData): AddressInterface
    {
        /** @var AddressInterface $address */
        $address = $this->addressFactory->createNew();

        $this->setBasicAddressData($address, $addressData);
        $this->setTemporaryPersonalData($address);

        return $address;
    }

    public function createFullAddress(array $addressData): AddressInterface
    {
        $address = $this->createTemporaryAddress($addressData);

        $this->setFullPersonalData($address, $addressData);

        return $address;
    }

    private function setBasicAddressData(AddressInterface $address, array $addressData): void
    {
        $address->setCity($addressData['locality'] ?? '');
        $address->setPostcode($addressData['postalCode'] ?? '');
        $address->setCountryCode($addressData['countryCode'] ?? '');
        $address->setProvinceName($addressData['administrativeArea'] ?? '');

        if (isset($addressData['phoneNumber']) && '' !== $addressData['phoneNumber']) {
            $address->setPhoneNumber($addressData['phoneNumber']);
        }
    }

    private function setTemporaryPersonalData(AddressInterface $address): void
    {
        $address->setFirstName('temp');
        $address->setLastName('temp');
        $address->setStreet('temp');
    }

    private function setFullPersonalData(AddressInterface $address, array $addressData): void
    {
        $name = explode(' ', $addressData['name'] ?? '');
        $address->setLastName(array_pop($name) ?? '');
        $address->setFirstName(implode(' ', $name));
        $address->setStreet(sprintf(
            '%s %s %s',
            $addressData['address1'] ?? '',
            $addressData['address2'] ?? '',
            $addressData['address3'] ?? '',
        ));
    }
}
