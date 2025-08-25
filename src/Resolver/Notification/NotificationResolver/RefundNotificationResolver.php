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
use Sylius\AdyenPlugin\Bus\Command\RefundPayment;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Webmozart\Assert\Assert;

final class RefundNotificationResolver implements CommandResolver
{
    public function __construct(
        private AdyenReferenceRepositoryInterface $adyenReferenceRepository,
    ) {
    }

    public function resolve(string $paymentMethodCode, NotificationItemData $notificationData): object
    {
        try {
            $reference = $this->adyenReferenceRepository->getOneForRefundByCodeAndReference(
                $paymentMethodCode,
                (string) $notificationData->pspReference,
            );

            $refundPayment = $reference->getRefundPayment();
            Assert::notNull($refundPayment);

            return new RefundPayment($refundPayment);
        } catch (\InvalidArgumentException|NoResultException) {
            throw new NoCommandResolvedException();
        }
    }
}
