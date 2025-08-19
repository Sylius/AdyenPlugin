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
use Sylius\AdyenPlugin\Bus\Command\ReversePayment;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodChecker;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReverseOrderPaymentProcessor implements OrderPaymentProcessorInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly StateMachineInterface $stateMachine,
    ) {
    }

    public function process(?OrderInterface $order): void
    {
        if (null === $order) {
            return;
        }

        $payment = $order->getLastPayment(PaymentInterface::STATE_COMPLETED);
        if (null !== $payment && AdyenPaymentMethodChecker::isAdyenPayment($payment)) {
            $this->commandBus->dispatch(new ReversePayment($payment));

            return;
        }

        // TODO: Add an additional cancel dispatch for authorized adyen payments if manual capture if enabled //
        $payment = $order->getLastPayment();
        if (null !== $payment && $this->stateMachine->can($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CANCEL)) {
            $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CANCEL);
        }
    }
}
