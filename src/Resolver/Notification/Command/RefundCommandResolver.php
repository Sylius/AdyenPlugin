<?php

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Resolver\Notification\Command;

use Doctrine\ORM\NoResultException;
use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\AdyenPlugin\Bus\Command\RefundPayment;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Webmozart\Assert\Assert;

final class RefundCommandResolver implements CommandResolverInterface
{
    public function __construct(
        private readonly AdyenReferenceRepositoryInterface $adyenReferenceRepository,
    ) {
    }

    public function resolve(string $paymentCode, NotificationItemData $notificationData): ?PaymentLifecycleCommand
    {
        try {
            $reference = $this->adyenReferenceRepository->getOneForRefundByCodeAndReference(
                $paymentCode,
                (string)$notificationData->pspReference,
            );
        } catch (NoResultException|\InvalidArgumentException) {
            return null;
        }

        $refundPayment = $reference->getRefundPayment();
        Assert::notNull($refundPayment);

        return new RefundPayment($refundPayment);
    }
}
