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

namespace Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver;

use Doctrine\ORM\NoResultException;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class PaymentNotificationResolver implements CommandResolver
{
    public function __construct(
        private readonly AdyenReferenceRepositoryInterface $adyenReferenceRepository,
        private readonly PaymentCommandFactoryInterface $commandFactory,
    ) {
    }

    public function resolve(string $paymentCode, NotificationItemData $notificationData): object
    {
        try {
            $payment = $this->fetchPayment(
                $paymentCode,
                (string) $notificationData->pspReference,
                $notificationData->originalReference,
            );

            return $this->commandFactory->createForEvent(
                (string) $notificationData->eventCode,
                $payment,
                $notificationData,
            );
        } catch (UnmappedAdyenActionException $ex) {
            throw new NoCommandResolvedException();
        }
    }

    private function fetchPayment(
        string $paymentCode,
        string $reference,
        ?string $originalReference,
    ): PaymentInterface {
        try {
            $reference = $this->adyenReferenceRepository->getOneByCodeAndReference(
                $paymentCode,
                $originalReference ?? $reference,
            );

            $result = $reference->getPayment();
            Assert::notNull($result);

            return $result;
        } catch (NoResultException $ex) {
            throw new NoCommandResolvedException();
        }
    }
}
