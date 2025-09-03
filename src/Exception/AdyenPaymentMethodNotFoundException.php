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

namespace Sylius\AdyenPlugin\Exception;

class AdyenPaymentMethodNotFoundException extends \RuntimeException
{
    public function __construct(string $paymentMethodCode)
    {
        parent::__construct(sprintf('Adyen payment method with code "%s" has not been found.', $paymentMethodCode));
    }
}
