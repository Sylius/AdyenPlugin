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

use Sylius\AdyenPlugin\Model\PaymentMethod;

interface PaymentMethodsFilterInterface
{
    /**
     * @param list<PaymentMethod> $paymentMethods
     * @param array<string, mixed> $context
     *
     * @return list<PaymentMethod>
     */
    public function filter(array $paymentMethods, array $context = []): array;
}
