<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Listener;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Event\PaymentAuthorisedEvent;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;

final class CompleteOrderOnPaymentAuthorisedListener
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly PaymentRepositoryInterface $paymentRepository,
    ) {
    }

    public function __invoke(PaymentAuthorisedEvent $event): void
    {
        /** @var PaymentInterface $payment */
        $payment = $this->paymentRepository->find($event->paymentId);
        if (null === $payment) {
            return;
        }

        if ($payment->getState() === PaymentInterface::STATE_COMPLETED) {
            return;
        }

        $order = $payment->getOrder();
        if (null === $order) {
            return;
        }

        // 2) (opcjonalnie) tylko „najnowsza”/aktywowana płatność zamówienia
        if ($order->getLastPayment() !== $payment) {
            return;
        }

        // 3) Zmiana stanu przez state machine (zamiast setState)
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);
        }
    }
}
