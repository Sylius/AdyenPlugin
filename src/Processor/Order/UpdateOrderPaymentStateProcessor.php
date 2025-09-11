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

namespace Sylius\AdyenPlugin\Processor\Order;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderPaymentTransitions;

final class UpdateOrderPaymentStateProcessor implements OrderPaymentProcessorInterface
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function process(?OrderInterface $order): void
    {
        $payment = $order?->getLastPayment();
        if (
            null === $order ||
            null === $payment ||
            !$this->adyenPaymentMethodChecker->isAdyenPayment($payment)
        ) {
            return;
        }

        if (
            $this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::AUTOMATIC) &&
            $order->getPaymentState() !== OrderPaymentStates::STATE_PAID
        ) {
            return;
        }

        if ($this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::MANUAL) &&
            !in_array($order->getPaymentState(), [OrderPaymentStates::STATE_PAID, OrderPaymentStates::STATE_AUTHORIZED], true)
        ) {
            return;
        }

        try {
            if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, 'cancel_adyen')) {
                $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, 'cancel_adyen');

                return;
            }
        } catch (\Exception) {
        }

        if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL)) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL);

            return;
        }

        if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, 'refund_adyen')) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, 'refund_adyen');

            return;
        }

        if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REFUND)) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REFUND);
        }
    }
}
