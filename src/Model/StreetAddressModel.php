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

namespace Sylius\AdyenPlugin\Model;

final class StreetAddressModel implements StreetAddressModelInterface
{
    /** @var string */
    private $street;

    /** @var string */
    private $houseNumber;

    public function __construct(string $street, string $houseNumberOrName)
    {
        $this->street = $street;
        $this->houseNumber = $houseNumberOrName;
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getHouseNumber(): string
    {
        return $this->houseNumber;
    }
}
