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

namespace Sylius\AdyenPlugin\Resolver\Notification\Struct;

class NotificationItemData
{
    /** @var ?array */
    public $additionalData;

    /** @var ?Amount */
    public $amount;

    /** @var ?string */
    public $eventCode;

    /** @var ?string */
    public $eventDate;

    /** @var ?string */
    public $merchantAccountCode;

    /** @var ?string */
    public $merchantReference;

    /** @var ?string */
    public $paymentMethod;

    /** @var ?bool */
    public $success;

    public string $pspReference;

    /** @var ?string */
    public $originalReference;

    /** @var ?array */
    public $operations;

    /** @var ?string */
    public $paymentCode;

    /** @var ?string */
    public $reason;
}
