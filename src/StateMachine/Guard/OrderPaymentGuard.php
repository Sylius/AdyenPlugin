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

namespace Sylius\AdyenPlugin\StateMachine\Guard;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OrderPaymentGuard
{
    public function __construct(
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function canBeCancelled(OrderInterface $order): bool
    {
        $payment = $order->getLastPayment();
        if (
            null === $payment ||
            false === $this->adyenPaymentMethodChecker->isAdyenPayment($payment)
        ) {
            return true;
        }

        if ($this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::AUTOMATIC)) {
            return $payment->getState() !== PaymentGraph::STATE_PROCESSING_REVERSAL;
        }
        if (in_array($payment->getState(), [PaymentInterface::STATE_PROCESSING, PaymentInterface::STATE_COMPLETED], true)) {
            return false;
        }

        return true;
    }
}
