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

final class StoredPaymentMethod
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        /** @var string[] */
        public readonly array $supportedShopperInteractions = [],
        public readonly ?string $brand = null,
        public readonly ?string $lastFour = null,
        public readonly ?string $expiryMonth = null,
        public readonly ?string $expiryYear = null,
        public readonly ?string $holderName = null,
    ) {
    }
}
