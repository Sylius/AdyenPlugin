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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Throwable;

class NonAdyenPaymentMethodException extends \InvalidArgumentException
{
    public function __construct(PaymentMethodInterface $paymentMethod, ?Throwable $previous = null)
    {
        parent::__construct(
            sprintf(
                'Provided PaymentMethod #%d is not an Adyen instance',
                (int) $paymentMethod->getId(),
            ),
            0,
            $previous,
        );
    }
}
