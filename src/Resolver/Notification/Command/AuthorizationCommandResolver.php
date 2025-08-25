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

use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\FailPayment;
use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\AdyenPlugin\Event\AdyenEventCode;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;

final class AuthorizationCommandResolver implements CommandResolverInterface
{
    public function resolve(string $paymentMethodCode, NotificationItemData $notificationItemData): PaymentLifecycleCommand
    {
        if ($notificationItemData->success === true) {
            return new AuthorizePayment($paymentMethodCode, $notificationItemData->merchantReference);
        }

        return new FailPayment($paymentMethodCode, $notificationItemData->merchantReference);
    }

    public function supports(NotificationItemData $notificationItemData): bool
    {
        return $notificationItemData->eventCode === AdyenEventCode::AUTHORISATION->value;
    }
}
