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

namespace Sylius\AdyenPlugin\EventListener\Workflow\Refund;

use Sylius\AdyenPlugin\Checker\Refund\RefundPaymentCompletionEligibilityCheckerInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Symfony\Component\Workflow\Event\GuardEvent;

final class GuardRefundPaymentCompletionListener
{
    public function __construct(
        private RefundPaymentCompletionEligibilityCheckerInterface $completionEligibilityChecker,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        $refundPayment = $event->getSubject();
        if (
            $refundPayment instanceof RefundPaymentInterface &&
            false === $this->completionEligibilityChecker->canBeCompleted($refundPayment)
        ) {
            $event->setBlocked(true);
        }
    }
}
