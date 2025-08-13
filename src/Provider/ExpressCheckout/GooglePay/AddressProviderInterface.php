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

use Sylius\Component\Core\Model\AddressInterface;

interface AddressProviderInterface
{
    public function createTemporaryAddress(array $addressData): AddressInterface;

    public function createFullAddress(array $addressData): AddressInterface;
}
