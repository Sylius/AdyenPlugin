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

namespace Sylius\AdyenPlugin\Modifier\ExpressCheckout\Paypal;

use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderAddressModifier implements OrderAddressModifierInterface
{
    public function __construct(
        private readonly AddressFactoryInterface $addressFactory,
    ) {
    }

    public function modify(
        OrderInterface $order,
        array $billingAddressData,
        array $shippingAddressData,
        array $payerData,
    ): void {
        $this->setAddress($order->getBillingAddress(), $billingAddressData, $payerData);
        $this->setAddress($order->getShippingAddress(), $billingAddressData, $payerData);
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
            $this->setTemporaryAddress($address, $addressData);

            $order->setShippingAddress($address);
            $order->setBillingAddress(clone $address);
        } else {
            $this->setTemporaryAddress($order->getBillingAddress(), $addressData);
            $this->setTemporaryAddress($order->getShippingAddress(), $addressData);
        }
    }

    private function setAddress(AddressInterface $address, array $addressData, array $payerData): void
    {
        if ('' !== ($addressData['firstName'] ?? '')) {
            $name = explode(' ', $addressData['firstName'] ?? '');
            $address->setLastName(array_pop($name) ?? '');
            $address->setFirstName(implode(' ', $name));
        } else {
            $address->setFirstName($payerData['name']['given_name'] ?? '');
            $address->setLastName($payerData['name']['surname'] ?? '');
        }

        $address->setCity($addressData['city'] ?? '');
        $address->setStreet(sprintf('%s %s', $addressData['street'] ?? '', $addressData['houseNumberOrName'] ?? ''));
        $address->setPostcode($addressData['postalCode'] ?? '');
        $address->setCountryCode($addressData['country'] ?? '');
        $address->setProvinceName($addressData['stateOrProvince'] ?? '');
        $address->setPhoneNumber($payerData['phone']['phone_number']['national_number'] ?? '');
    }

    private function setTemporaryAddress(AddressInterface $address, array $addressData): void
    {
        $address->setCity($addressData['city'] ?? '');
        $address->setPostcode($addressData['postalCode'] ?? '');
        $address->setCountryCode($addressData['countryCode'] ?? '');
        $address->setProvinceName($addressData['state'] ?? '');
    }
}
