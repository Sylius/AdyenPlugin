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

namespace Sylius\AdyenPlugin\Controller\Shop;

use Psr\Log\LoggerInterface;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver\NoCommandResolvedException;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationToCommandResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class ProcessNotificationsAction
{
    public const EXPECTED_ADYEN_RESPONSE = '[accepted]';

    public function __construct(
        private readonly NotificationToCommandResolver $notificationCommandResolver,
        private readonly NotificationResolver $notificationResolver,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(string $code, Request $request): Response
    {
        foreach ($this->notificationResolver->resolve($code, $request) as $notificationItem) {
            if (null === $notificationItem || false === $notificationItem->success) {
                $this->logger->error(\sprintf(
                    'Payment with pspReference [%s] did not return success',
                    $notificationItem->pspReference ?? '',
                ));
            } else {
                $this->logger->debug(\sprintf(
                    'Payment with pspReference [%s] finished with event code [%s]',
                    $notificationItem->pspReference ?? '',
                    $notificationItem->eventCode ?? '',
                ));
            }

            try {
                $command = $this->notificationCommandResolver->resolve($code, $notificationItem);
                $this->messageBus->dispatch($command);
            } catch (NoCommandResolvedException $ex) {
                $this->logger->error('Tried to dispatch an unknown command');
            }
        }

        return new Response(self::EXPECTED_ADYEN_RESPONSE);
    }
}
