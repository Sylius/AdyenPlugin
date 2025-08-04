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

use Throwable;

class RefundNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $reference, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('Cannot find reference for refund "%s"', $reference), 0, $previous);
    }
}
