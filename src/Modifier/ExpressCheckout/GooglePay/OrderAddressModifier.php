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

namespace Sylius\AdyenPlugin\Modifier\ExpressCheckout\GooglePay;

use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderAddressModifier implements OrderAddressModifierInterface
{
    public function __construct(
        private readonly AddressFactoryInterface $addressFactory,
    ) {
    }

    public function modify(OrderInterface $order, array $addressData): void
    {
        $this->setAddress($order->getBillingAddress(), $addressData);
        $this->setAddress($order->getShippingAddress(), $addressData);
    }

    public function modifyTemporaryAddress(
        OrderInterface $order,
        array $addressData,
    ): void {
        if (null === $order->getBillingAddress()) {
            /** @var AddressInterface $address */
            $address = $this->addressFactory->createNew();
            $address->setFirstName('temp');
            $address->setLastName('temp');
            $address->setStreet('temp');
            $this->setBasicAddressData($address, $addressData);

            $order->setShippingAddress($address);
            $order->setBillingAddress(clone $address);
        } else {
            $this->setBasicAddressData($order->getBillingAddress(), $addressData);
            $this->setBasicAddressData($order->getShippingAddress(), $addressData);
        }
    }

    private function setAddress(AddressInterface $address, array $addressData): void
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

        $this->setBasicAddressData($address, $addressData);

        if (isset($addressData['phoneNumber']) && '' !== $addressData['phoneNumber']) {
            $address->setPhoneNumber($addressData['phoneNumber']);
        }
    }

    private function setBasicAddressData(AddressInterface $address, array $addressData): void
    {
        $address->setCity($addressData['locality'] ?? '');
        $address->setPostcode($addressData['postalCode'] ?? '');
        $address->setCountryCode($addressData['countryCode'] ?? '');
        $address->setProvinceName($addressData['administrativeArea'] ?? '');
    }
}
