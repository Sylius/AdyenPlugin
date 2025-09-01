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

namespace Sylius\AdyenPlugin\Filter;

use Sylius\AdyenPlugin\Model\AvailablePaymentMethod;
use Sylius\AdyenPlugin\Model\StoredPaymentMethod;

final class StoredPaymentMethodsFilter implements StoredPaymentMethodsFilterInterface
{
    public function filterAgainstAvailable(array $stored, array $available): array
    {
        $availableTypes = array_flip(array_map(fn (AvailablePaymentMethod $method) => $method->type, $available));

        return array_values(array_filter(
            $stored,
            fn (StoredPaymentMethod $storedPaymentMethod) => isset($availableTypes[$storedPaymentMethod->type]),
        ));
    }
}
