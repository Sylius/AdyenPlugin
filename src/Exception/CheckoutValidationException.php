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

final class CheckoutValidationException extends \InvalidArgumentException
{
    public function __construct(string $message = 'Order validation failed during checkout complete step.', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
