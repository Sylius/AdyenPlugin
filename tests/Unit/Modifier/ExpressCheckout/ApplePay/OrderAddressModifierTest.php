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

namespace Tests\Sylius\AdyenPlugin\Unit\Modifier\ExpressCheckout\ApplePay;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\ApplePay\OrderAddressModifier;
use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderAddressModifierTest extends TestCase
{
    private AddressFactoryInterface&MockObject $addressFactory;
    private OrderAddressModifier $orderAddressModifier;

    protected function setUp(): void
    {
        $this->addressFactory = $this->createMock(AddressFactoryInterface::class);
        $this->orderAddressModifier = new OrderAddressModifier($this->addressFactory);
    }

    public function testModifyUpdatesExistingAddresses(): void
    {
        $billingAddress = $this->createMock(AddressInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        $addressData = [
            'givenName' => 'John',
            'familyName' => 'Doe',
            'addressLines' => ['123 Main St', 'Apt 4B'],
            'locality' => 'New York',
            'postalCode' => '10001',
            'countryCode' => 'US',
            'administrativeArea' => 'NY',
        ];

        $billingAddress->expects($this->once())->method('setFirstName')->with('John');
        $billingAddress->expects($this->once())->method('setLastName')->with('Doe');
        $billingAddress->expects($this->once())->method('setStreet')->with('123 Main St');
        $billingAddress->expects($this->once())->method('setCity')->with('New York');
        $billingAddress->expects($this->once())->method('setPostcode')->with('10001');
        $billingAddress->expects($this->once())->method('setCountryCode')->with('US');
        $billingAddress->expects($this->once())->method('setProvinceName')->with('NY');

        $shippingAddress->expects($this->once())->method('setFirstName')->with('John');
        $shippingAddress->expects($this->once())->method('setLastName')->with('Doe');
        $shippingAddress->expects($this->once())->method('setStreet')->with('123 Main St');
        $shippingAddress->expects($this->once())->method('setCity')->with('New York');
        $shippingAddress->expects($this->once())->method('setPostcode')->with('10001');
        $shippingAddress->expects($this->once())->method('setCountryCode')->with('US');
        $shippingAddress->expects($this->once())->method('setProvinceName')->with('NY');

        $this->orderAddressModifier->modify($order, $addressData);
    }

    public function testModifyCreatesNewAddressesWhenNull(): void
    {
        $newAddress = $this->createMock(AddressInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $this->addressFactory->expects($this->exactly(2))
            ->method('createNew')
            ->willReturn($newAddress);

        $addressData = [
            'givenName' => 'Jane',
            'familyName' => 'Smith',
            'addressLines' => ['456 Oak Ave'],
            'locality' => 'Los Angeles',
            'postalCode' => '90001',
            'countryCode' => 'US',
            'administrativeArea' => 'CA',
        ];

        $newAddress->expects($this->exactly(2))->method('setFirstName')->with('Jane');
        $newAddress->expects($this->exactly(2))->method('setLastName')->with('Smith');
        $newAddress->expects($this->exactly(2))->method('setStreet')->with('456 Oak Ave');
        $newAddress->expects($this->exactly(2))->method('setCity')->with('Los Angeles');
        $newAddress->expects($this->exactly(2))->method('setPostcode')->with('90001');
        $newAddress->expects($this->exactly(2))->method('setCountryCode')->with('US');
        $newAddress->expects($this->exactly(2))->method('setProvinceName')->with('CA');

        $this->orderAddressModifier->modify($order, $addressData);
    }

    public function testModifyHandlesMissingAddressData(): void
    {
        $billingAddress = $this->createMock(AddressInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        $addressData = [];

        $billingAddress->expects($this->once())->method('setFirstName')->with('');
        $billingAddress->expects($this->once())->method('setLastName')->with('');
        $billingAddress->expects($this->once())->method('setStreet')->with('');
        $billingAddress->expects($this->once())->method('setCity')->with('');
        $billingAddress->expects($this->once())->method('setPostcode')->with('');
        $billingAddress->expects($this->once())->method('setCountryCode')->with('');
        $billingAddress->expects($this->once())->method('setProvinceName')->with('');

        $shippingAddress->expects($this->once())->method('setFirstName')->with('');
        $shippingAddress->expects($this->once())->method('setLastName')->with('');
        $shippingAddress->expects($this->once())->method('setStreet')->with('');
        $shippingAddress->expects($this->once())->method('setCity')->with('');
        $shippingAddress->expects($this->once())->method('setPostcode')->with('');
        $shippingAddress->expects($this->once())->method('setCountryCode')->with('');
        $shippingAddress->expects($this->once())->method('setProvinceName')->with('');

        $this->orderAddressModifier->modify($order, $addressData);
    }

    public function testModifyTemporaryAddressCreatesNewAddressesWhenNull(): void
    {
        $newAddress = $this->createMock(AddressInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order->method('getBillingAddress')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);

        $this->addressFactory->expects($this->once())->method('createNew')->willReturn($newAddress);

        $addressData = [
            'locality' => 'Chicago',
            'postalCode' => '60601',
            'countryCode' => 'US',
            'administrativeArea' => 'IL',
        ];

        $newAddress->expects($this->once())->method('setFirstName')->with('temp');
        $newAddress->expects($this->once())->method('setLastName')->with('temp');
        $newAddress->expects($this->once())->method('setStreet')->with('temp');
        $newAddress->expects($this->once())->method('setCity')->with('Chicago');
        $newAddress->expects($this->once())->method('setPostcode')->with('60601');
        $newAddress->expects($this->once())->method('setCountryCode')->with('US');
        $newAddress->expects($this->once())->method('setProvinceName')->with('IL');

        $order->expects($this->once())->method('setShippingAddress')->with($newAddress);
        $order->expects($this->once())->method('setBillingAddress');

        $this->orderAddressModifier->modifyTemporaryAddress($order, $addressData);
    }

    public function testModifyTemporaryAddressUpdatesExistingAddresses(): void
    {
        $billingAddress = $this->createMock(AddressInterface::class);
        $shippingAddress = $this->createMock(AddressInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        $addressData = [
            'locality' => 'Boston',
            'postalCode' => '02101',
            'countryCode' => 'US',
            'administrativeArea' => 'MA',
        ];

        $billingAddress->expects($this->once())->method('setCity')->with('Boston');
        $billingAddress->expects($this->once())->method('setPostcode')->with('02101');
        $billingAddress->expects($this->once())->method('setCountryCode')->with('US');
        $billingAddress->expects($this->once())->method('setProvinceName')->with('MA');

        $shippingAddress->expects($this->once())->method('setCity')->with('Boston');
        $shippingAddress->expects($this->once())->method('setPostcode')->with('02101');
        $shippingAddress->expects($this->once())->method('setCountryCode')->with('US');
        $shippingAddress->expects($this->once())->method('setProvinceName')->with('MA');

        $this->orderAddressModifier->modifyTemporaryAddress($order, $addressData);
    }
}
