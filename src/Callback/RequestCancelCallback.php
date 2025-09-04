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

namespace Sylius\AdyenPlugin\Callback;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class RequestCancelCallback
{
    public function __construct(
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private readonly StateMachineInterface $stateMachine,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(OrderInterface $order): void
    {
        $payment = $order->getLastPayment();
        if (
            null === $payment ||
            !$this->adyenPaymentMethodChecker->isAdyenPayment($payment)
        ) {
            return;
        }
        if ($this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::AUTOMATIC)) {
            return;
        }

        if (
            $this->stateMachine->can($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS) &&
            !isset($payment->getDetails()[CancelPayment::PROCESSING_CANCELLATION])
        ) {
            $this->messageBus->dispatch(new CancelPayment($order));
        }
    }
}
