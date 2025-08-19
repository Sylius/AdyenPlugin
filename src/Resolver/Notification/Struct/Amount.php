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

namespace Sylius\AdyenPlugin\Resolver\Notification\Struct;

/**
 * Value object representing an amount in Adyen minor units.
 *
 * - $currency is an ISO 4217 code (e.g. "EUR").
 * - $value is an integer in minor units (e.g. 1234 => 12.34 EUR).
 */
final class Amount
{
    public function __construct(
        public readonly string $currency,
        public readonly int $value,
    ) {
        if (!\preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO 4217 code.');
        }
    }
}
