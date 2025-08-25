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

namespace Sylius\AdyenPlugin\Bus\Command;

use Sylius\AdyenPlugin\Model\PaymentOutcome;

final class PaymentStatusReceived implements PaymentLifecycleCommand
{
    public function __construct(public readonly PaymentOutcome $paymentOutcome)
    {
    }
}
