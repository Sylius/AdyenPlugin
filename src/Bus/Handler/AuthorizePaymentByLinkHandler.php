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

namespace Sylius\AdyenPlugin\Bus\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePaymentByLink;
use Sylius\AdyenPlugin\Bus\Command\CreateReferenceForPayment;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class AuthorizePaymentByLinkHandler
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(AuthorizePaymentByLink $authorizePaymentByLink): void
    {
        $payment = $authorizePaymentByLink->payment;
        $notificationItemData = $authorizePaymentByLink->notificationItemData;

        $normalizedData = $this->normalizer->normalize($notificationItemData, 'array');
        $payment->setDetails($normalizedData);

        $this->commandBus->dispatch(new CreateReferenceForPayment($payment));
        $this->commandBus->dispatch(new AuthorizePayment($payment));

        $this->entityManager->flush();
    }
}
