<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Event;

/** @see https://docs.adyen.com/online-payments/build-your-integration/payment-result-codes/ */
enum ResultCodeType: string
{
    // Final state result

    case AUTHORISED = 'Authorised';
    case CANCELLED = 'Cancelled';
    case ERROR = 'Error';
    case REFUSED = 'Refused';
}
