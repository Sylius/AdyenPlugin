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

namespace Sylius\AdyenPlugin;

use Sylius\Component\Payment\PaymentTransitions as BasePaymentTransitions;

interface PaymentGraph extends BasePaymentTransitions
{
    public const STATE_PROCESSING_REVERSAL = 'processing_reversal';

    public const TRANSITION_CAPTURE = 'capture';

    public const TRANSITION_REVERSE = 'reverse';
}
