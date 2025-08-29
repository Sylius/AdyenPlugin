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

namespace Sylius\AdyenPlugin\Resolver\ExpressCheckout;

use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Webmozart\Assert\Assert;

final class CheckoutResolver implements CheckoutResolverInterface
{
    public function __construct(
        private readonly ObjectManager $orderManager,
        private readonly StateMachineInterface $stateMachine,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function resolve(OrderInterface $order): void
    {
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);

        if ($order->isShippingRequired()) {
            $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        }

        $paymentMethod = $this->paymentMethodRepository->findOneByChannel($order->getChannel());
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);
        $order->getLastPayment(PaymentInterface::STATE_CART)->setMethod($paymentMethod);
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        $this->orderManager->flush();
    }
}
