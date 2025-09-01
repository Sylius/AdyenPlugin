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

final class AvailablePaymentMethod
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $name = null,
        /** @var string[] */
        public readonly array $brands = [],
        public readonly ?array $configuration = null,
        public readonly ?array $issuers = null,
        public readonly ?array $details = null,
    ) {
    }
}
