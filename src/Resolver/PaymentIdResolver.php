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

namespace Sylius\AdyenPlugin\Resolver;

use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;

final class PaymentIdResolver implements PaymentIdResolverInterface
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function resolveFromNotification(NotificationItemData $notificationItemData): mixed
    {
        if ($notificationItemData->merchantReference !== null && $notificationItemData->merchantReference !== '') {
            $order = $this->orderRepository->findOneBy(['number' => $notificationItemData->merchantReference]);

            if (null !== $order && $order->getLastPayment()) {
                return (string) $order->getLastPayment()->getId();
            }
        }

        return null;
    }
}
