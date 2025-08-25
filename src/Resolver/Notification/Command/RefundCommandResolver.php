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
    )
    {
    }

    public function resolve(string $paymentMethodCode, NotificationItemData $notificationItemData): PaymentLifecycleCommand
    {
        $reference = $this->adyenReferenceRepository->getOneForRefundByCodeAndReference(
            $paymentMethodCode,
            $notificationItemData->pspReference,
        );

        $refundPayment = $reference->getRefundPayment();
        Assert::notNull($refundPayment);

        return new RefundPayment($refundPayment);
    }

    public function supports(NotificationItemData $notificationItemData): bool
    {
        return false;
    }
}
