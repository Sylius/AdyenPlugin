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
        if (self::EVENT_CANCEL_OR_REFUND === $notificationData->eventCode) {
            return $this->resolveReversal($notificationData);
        }

        if (self::EVENT_AUTHORIZATION !== $notificationData->eventCode) {
            return (string) $notificationData->eventCode;
        }

        // Event for pay by link has no connection to a payment since there's no reference on our side yet
        if (isset($notificationData->additionalData['paymentLinkId'])) {
            return self::EVENT_PAY_BY_LINK_AUTHORISATION;
        }

        return self::EVENT_AUTHORIZATION;
    }

    private function resolveReversal(NotificationItemData $notificationData): string
    {
        $modificationAction = $notificationData->additionalData['modification.action'] ?? null;

        return match ($modificationAction) {
            self::MODIFICATION_CANCEL => self::EVENT_CANCELLATION,
            self::MODIFICATION_REFUND => self::EVENT_REFUND,
            default => (string) $notificationData->eventCode,
        };
    }
}
