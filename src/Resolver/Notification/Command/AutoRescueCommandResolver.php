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

namespace Sylius\AdyenPlugin\Resolver\Notification\Command;

use Sylius\AdyenPlugin\Command\AutoRescue\AutoRescueSuccess;
use Sylius\AdyenPlugin\Command\AutoRescue\FlagPaymentRescueScheduled;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;

final class AutoRescueCommandResolver implements CommandResolverInterface
{
    public function resolve(string $paymentMethodCode, NotificationItemData $notificationItemData): PaymentLifecycleCommand
    {
        $raw = $notificationItemData->additionalData['retry.rescueScheduled'] ?? null;
        $rescue = is_bool($raw) ? $raw : (filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false);

        if ($notificationItemData->success === false && $rescue === true) {
            return new FlagPaymentRescueScheduled(
                merchantReference: $notificationItemData->merchantReference,
                pspReference: $notificationItemData->pspReference,
                rescueReference: (string) ($notificationItemData->additionalData['retry.rescueReference'] ?? ''),
            );
        }

        if ($notificationItemData->success === true && $rescue === false) {
            return new AutoRescueSuccess(
                merchantReference: $notificationItemData->merchantReference,
                pspReference: $notificationItemData->pspReference,
            );
        }

        throw new \InvalidArgumentException(sprintf(
            'Cannot resolve command for payment method "%s" with event code "%s".',
            $paymentMethodCode,
            $notificationItemData->eventCode->value,
        ));
    }

    public function supports(NotificationItemData $notificationItemData): bool
    {
        return array_key_exists('retry.rescueScheduled', $notificationItemData->additionalData) === false;
    }
}
