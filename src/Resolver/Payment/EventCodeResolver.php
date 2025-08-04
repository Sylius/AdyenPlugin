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

namespace Sylius\AdyenPlugin\Resolver\Payment;

use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;

final class EventCodeResolver implements EventCodeResolverInterface
{
    public function resolve(NotificationItemData $notificationData): string
    {
        if (self::AUTHORIZATION !== $notificationData->eventCode) {
            return (string) $notificationData->eventCode;
        }

        // Adyen doesn't provide a "card" payment method name but specifies a brand for each, so make it generic
        if (isset($notificationData->additionalData['expiryDate'])) {
            return self::AUTHORIZATION;
        }

        return self::PAYMENT_METHOD_TYPES[$notificationData->paymentMethod] ?? self::CAPTURE;
    }
}
