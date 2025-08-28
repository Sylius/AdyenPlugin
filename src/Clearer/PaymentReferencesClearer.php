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

namespace Sylius\AdyenPlugin\Clearer;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentReferencesClearer implements PaymentReferencesClearerInterface
{
    public function __construct(
        private readonly AdyenReferenceRepositoryInterface $adyenReferenceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function clear(PaymentInterface $payment): void
    {
        if (!$this->shouldBeCleared($payment)) {
            return;
        }

        $payment->setDetails([]);

        $references = $this->adyenReferenceRepository->findAllByPayment($payment);
        foreach ($references as $reference) {
            $this->entityManager->remove($reference);
        }

        $this->entityManager->flush();
    }

    private function shouldBeCleared(PaymentInterface $payment): bool
    {
        return in_array($payment->getState(), [
            PaymentInterface::STATE_CART,
            PaymentInterface::STATE_NEW,
        ], true);
    }
}
