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

final class CompositePaymentMethodsFilter implements PaymentMethodsFilterInterface
{
    /** @param iterable<PaymentMethodsFilterInterface> $filters */
    public function __construct(
        private iterable $filters,
    ) {
    }

    public function filter(array $paymentMethods, array $context = []): array
    {
        foreach ($this->filters as $filter) {
            $paymentMethods = $filter->filter($paymentMethods, $context);
        }

        return $paymentMethods;
    }
}
