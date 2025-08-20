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
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;

final class OrderPaymentGuard
{
    public function __construct(
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function canBeCancelled(OrderInterface $order): bool
    {
        $payment = $order->getLastPayment();
        // TODO: Possibly add a manual capture toggle in the payment method configuration and check it here to disallow cancelling paid orders in these cases //
        if (
            null !== $payment &&
            $this->adyenPaymentMethodChecker->isAdyenPayment($payment) &&
            $payment->getState() !== PaymentGraph::STATE_PROCESSING_REVERSAL
        ) {
            return true;
        }

        return false;
    }
}
