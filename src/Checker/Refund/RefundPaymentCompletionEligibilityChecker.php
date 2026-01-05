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

namespace Sylius\AdyenPlugin\Checker\Refund;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

final class RefundPaymentCompletionEligibilityChecker implements RefundPaymentCompletionEligibilityCheckerInterface
{
    public function __construct(
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function canBeCompleted(RefundPaymentInterface $refundPayment): bool
    {
        return false === $this->adyenPaymentMethodChecker->isAdyenPaymentMethod($refundPayment->getPaymentMethod());
    }
}
