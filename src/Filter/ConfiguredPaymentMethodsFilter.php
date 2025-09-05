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

final class ConfiguredPaymentMethodsFilter implements PaymentMethodsFilterInterface
{
    public function __construct(
        private readonly array $allowedTypes,
    ) {
    }

    public function filter(array $paymentMethods): array
    {
        if ($this->allowedTypes === []) {
            return $paymentMethods;
        }

        return array_values(array_filter($paymentMethods, function ($method): bool {
            return in_array($method->type, $this->allowedTypes, true);
        }));
    }
}
