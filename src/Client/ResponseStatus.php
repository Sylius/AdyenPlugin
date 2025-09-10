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

namespace Sylius\AdyenPlugin\Client;

interface ResponseStatus
{
    public const ACTIVE = 'active';

    public const AUTHORISED = 'authorised';

    public const CANCELLED = 'cancelled';

    public const ERROR = 'error';

    public const PAYMENT_STATUS_RECEIVED = 'payment_status_received';

    public const PROCESSING = 'processing';

    public const RECEIVED = 'received';

    public const REDIRECT_SHOPPER = 'redirectshopper';

    public const REFUSED = 'refused';

    public const REJECTED = 'rejected';
}
