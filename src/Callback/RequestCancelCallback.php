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

use SM\Factory\FactoryInterface;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Symfony\Component\Messenger\MessageBusInterface;

final class RequestCancelCallback
{
    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(OrderInterface $order): void
    {
        $factory = $this->factory->get($order, OrderPaymentTransitions::GRAPH);

        if (!$factory->can(OrderPaymentTransitions::TRANSITION_CANCEL)) {
            return;
        }

        $this->messageBus->dispatch(
            new CancelPayment($order),
        );
    }
}
