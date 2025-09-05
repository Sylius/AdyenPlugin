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

final class PaymentMethodData
{
    public function __construct(
        /** @var PaymentMethod[] */
        public readonly array $paymentMethods,
        /** @var StoredPaymentMethod[] */
        public readonly array $storedPaymentMethods,
    ) {
    }
}
