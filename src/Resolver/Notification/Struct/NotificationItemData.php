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
    public ?array $additionalData = null;

    public ?Amount $amount = null;

    public ?string $eventCode = null;

    public ?string $eventDate = null;

    public ?string $merchantAccountCode = null;

    public ?string $merchantReference = null;

    public ?string $paymentMethod = null;

    public bool|string|null $success = null;

    public ?string $pspReference = null;

    public ?string $originalReference = null;

    public ?array $operations = null;

    public ?string $paymentCode = null;

    public ?string $reason = null;
}
