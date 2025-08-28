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

use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver\CommandResolver;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver\NoCommandResolvedException;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;

final class NotificationToCommandResolver implements NotificationToCommandResolverInterface
{
    /**
     * @param iterable<int, CommandResolver> $commandResolvers
     */
    public function __construct(private readonly iterable $commandResolvers)
    {
    }

    public function resolve(string $paymentCode, NotificationItemData $notificationData): object
    {
        foreach ($this->commandResolvers as $resolver) {
            try {
                return $resolver->resolve($paymentCode, $notificationData);
            } catch (NoCommandResolvedException) {
            }
        }

        throw new NoCommandResolvedException();
    }
}
