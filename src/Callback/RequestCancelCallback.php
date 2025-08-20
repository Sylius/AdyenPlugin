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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Symfony\Component\Messenger\MessageBusInterface;

final class RequestCancelCallback
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(OrderInterface $order): void
    {
        if (
            $this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_CANCEL)
        ) {
            $this->messageBus->dispatch(new CancelPayment($order));
        }
    }
}
