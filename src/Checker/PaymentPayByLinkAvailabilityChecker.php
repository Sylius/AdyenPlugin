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

namespace Sylius\AdyenPlugin\Checker;

use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentPayByLinkAvailabilityChecker implements PaymentPayByLinkAvailabilityCheckerInterface
{
    public function __construct(
        private PaymentLinkRepositoryInterface $paymentLinkRepository,
        private AdyenReferenceRepositoryInterface $referenceRepository,
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private array $allowedStates = [PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING],
    ) {
    }

    public function canBeGenerated(PaymentInterface $payment): bool
    {
        if (!$this->adyenPaymentMethodChecker->isAdyenPayment($payment)) {
            return false;
        }

        if (!in_array($payment->getState(), $this->allowedStates, true)) {
            return false;
        }

        if (0 !== count($this->paymentLinkRepository->findBy(['payment' => $payment], limit: 1))) {
            return true;
        }

        if (0 === count($this->referenceRepository->findBy(['payment' => $payment], limit: 1))) {
            return true;
        }

        return false;
    }
}
