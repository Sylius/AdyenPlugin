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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class OrderGuard
{
    public function __construct(
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function canBeCancelled(OrderInterface $order): bool
    {
        foreach ($order->getPayments() as $payment) {
            if (
                $this->adyenPaymentMethodChecker->isAdyenPayment($payment) &&
                $this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::MANUAL) &&
                $payment->getState() === PaymentInterface::STATE_PROCESSING
            ) {
                return false;
            }
        }

        return true;
    }
}
