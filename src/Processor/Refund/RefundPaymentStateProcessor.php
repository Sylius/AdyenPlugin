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

namespace Sylius\AdyenPlugin\Processor\Refund;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

final class RefundPaymentStateProcessor implements RefundPaymentStateProcessorInterface
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function process(RefundPaymentInterface $refundPayment): void
    {
        if (!$this->adyenPaymentMethodChecker->isAdyenPaymentMethod($refundPayment->getPaymentMethod())) {
            return;
        }

        $order = $refundPayment->getOrder();
        $payment = $order->getLastPayment();
        if (
            null === $payment ||
            !$this->adyenPaymentMethodChecker->isAdyenPayment($payment) ||
            $refundPayment->getAmount() !== $payment->getAmount() ||
            $refundPayment->getCurrencyCode() !== $payment->getCurrencyCode()
        ) {
            return;
        }

        if ($this->stateMachine->can($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REFUND)) {
            $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REFUND);
        }
    }
}
