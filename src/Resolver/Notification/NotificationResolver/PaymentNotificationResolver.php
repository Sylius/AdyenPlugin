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
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class PaymentNotificationResolver implements CommandResolver
{
    public function __construct(
        private readonly AdyenReferenceRepositoryInterface $adyenReferenceRepository,
        private readonly PaymentLinkRepositoryInterface $paymentLinkRepository,
        private readonly PaymentCommandFactoryInterface $commandFactory,
    ) {
    }

    public function resolve(string $paymentMethodCode, NotificationItemData $notificationData): object
    {
        try {
            $payment = $this->getPayment($paymentMethodCode, $notificationData);

            return $this->commandFactory->createForEvent(
                (string) $notificationData->eventCode,
                $payment,
                $notificationData,
            );
        } catch (UnmappedAdyenActionException $ex) {
            throw new NoCommandResolvedException();
        }
    }

    private function getPayment(
        string $paymentMethodCode,
        NotificationItemData $notificationData,
    ): PaymentInterface {
        try {
            return $this->fetchPaymentByReference($paymentMethodCode, $notificationData);
        } catch (NoCommandResolvedException) {
            return $this->fetchPaymentByPaymentLink($paymentMethodCode, $notificationData);
        }
    }

    private function fetchPaymentByReference(
        string $paymentCode,
        NotificationItemData $notificationData,
    ): PaymentInterface {
        try {
            $reference = $this->adyenReferenceRepository->getOneByCodeAndReference(
                $paymentCode,
                $notificationData->originalReference ?? (string) $notificationData->pspReference,
            );

            $result = $reference->getPayment();
            Assert::notNull($result);

            return $result;
        } catch (NoResultException $ex) {
            throw new NoCommandResolvedException();
        }
    }

    private function fetchPaymentByPaymentLink(
        string $paymentMethodCode,
        NotificationItemData $notificationData,
    ): PaymentInterface {
        $paymentLinkId = $notificationData->additionalData['paymentLinkId'] ?? null;
        if (!isset($paymentLinkId)) {
            throw new NoCommandResolvedException('Payment link ID is not provided in the notification data.');
        }

        $result = $this->paymentLinkRepository->findOneByPaymentMethodCodeAndLinkId(
            $paymentMethodCode,
            $paymentLinkId,
        );
        if (null === $result) {
            throw new NoCommandResolvedException();
        }

        return $result->getPayment();
    }
}
