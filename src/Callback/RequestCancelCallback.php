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
use Sylius\AdyenPlugin\Bus\Dispatcher;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentTransitions;

final class RequestCancelCallback
{
    /** @var Dispatcher */
    private $dispatcher;

    /** @var FactoryInterface */
    private $factory;

    public function __construct(
        FactoryInterface $factory,
        Dispatcher $dispatcher,
    ) {
        $this->dispatcher = $dispatcher;
        $this->factory = $factory;
    }

    public function __invoke(OrderInterface $order): void
    {
        $factory = $this->factory->get($order, OrderPaymentTransitions::GRAPH);

        if (!$factory->can(OrderPaymentTransitions::TRANSITION_CANCEL)) {
            return;
        }

        $this->dispatcher->dispatch(
            new CancelPayment($order),
        );
    }
}
