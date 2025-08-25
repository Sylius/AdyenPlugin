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

namespace Sylius\AdyenPlugin\Resolver\Notification;

use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\AdyenPlugin\Exception\NoCommandResolvedException;
use Sylius\AdyenPlugin\Resolver\Notification\Command\CommandResolverInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;

final class NotificationToCommandResolver implements NotificationToCommandResolverInterface
{
    /** @param iterable<CommandResolverInterface> $commandResolvers */
    public function __construct(private readonly iterable $commandResolvers)
    {
    }

    public function resolve(string $paymentMethodCode, NotificationItemData $notificationItemData): PaymentLifecycleCommand
    {
        foreach ($this->commandResolvers as $commandResolver) {
            if ($commandResolver->supports($notificationItemData)) {
                return $commandResolver->resolve($paymentMethodCode, $notificationItemData);
            }
        }

        throw new NoCommandResolvedException();
    }
}
