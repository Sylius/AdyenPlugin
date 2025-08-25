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
use Sylius\AdyenPlugin\Classifier\PaymentResultClassifierInterface;
use Sylius\AdyenPlugin\Dispatcher\PaymentResultDispatcherInterface;
use Sylius\AdyenPlugin\Enum\PaymentResultType;
use Sylius\AdyenPlugin\Event\PaymentOutcomeEvent;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolverInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\AdyenPlugin\Resolver\PaymentIdResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProcessNotificationsAction
{
    public const EXPECTED_ADYEN_RESPONSE = '[accepted]';

    public function __construct(
        private readonly NotificationResolverInterface $notificationResolver,
        private readonly LoggerInterface $logger,
        private readonly PaymentIdResolverInterface $paymentIdResolver,
        private readonly PaymentResultClassifierInterface $paymentResultClassifier,
        private readonly PaymentResultDispatcherInterface $paymentResultDispatcher,
    )
    {
    }

    public function __invoke(string $paymentMethodCode, Request $request): Response
    {
        foreach ($this->notificationResolver->resolve($paymentMethodCode, $request) as $notificationItem) {
            $this->logProcessingNotification($notificationItem);

            $paymentId = $this->paymentIdResolver->resolveFromNotification($notificationItem);
            if (null === $paymentId) {
                $this->logger->warning(sprintf(
                    'Cannot resolve paymentId for eventCode [%s], pspReference [%s], merchantReference [%s]',
                    $n->eventCode ?? '', $n->pspReference ?? '', $n->merchantReference ?? '',
                ));
                continue;
            }

            $paymentResult = $this->paymentResultClassifier->classify($paymentId, [
                'eventCode' => $notificationItem->eventCode,
                'success' => (bool)$notificationItem->success,
            ]);

            $this->paymentResultDispatcher->dispatch($paymentResult);
        }

        return new Response(self::EXPECTED_ADYEN_RESPONSE);
    }

    private function logProcessingNotification(NotificationItemData $notificationItemData): void
    {
        if (false === $notificationItemData->success) {
            $this->logger->error(\sprintf(
                'Payment with pspReference [%s] did not return success',
                $notificationItemData->pspReference ?? '',
            ));
        } else {
            $this->logger->debug(\sprintf(
                'Payment with pspReference [%s] finished with event code [%s]',
                $notificationItemData->pspReference ?? '',
                $notificationItemData->eventCode ?? '',
            ));
        }
    }
}
