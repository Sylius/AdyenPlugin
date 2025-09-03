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

class NoShippingMethodsAvailableException extends \InvalidArgumentException
{
    public function __construct(string $message = 'No shipping methods available for the order.')
    {
        parent::__construct($message);
    }
}
