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

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;

final readonly class RefundEligibilityChecker implements RefundEligibilityCheckerInterface
{
    public function __construct(
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function canRefund(OrderInterface $order): bool
    {
        if ($order->getState() !== OrderInterface::STATE_FULFILLED) {
            return false;
        }

        $payment = $order->getLastPayment();
        if (null === $payment) {
            return false;
        }

        if (!$this->adyenPaymentMethodChecker->isAdyenPayment($payment)) {
            return false;
        }

        if (!$this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::AUTOMATIC)) {
            return false;
        }

        if (!$this->stateMachine->can($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REVERSE)) {
            return false;
        }

        return true;
    }
}
