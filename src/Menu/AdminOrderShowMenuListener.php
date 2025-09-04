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

namespace Sylius\AdyenPlugin\Menu;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Bundle\AdminBundle\Event\OrderShowMenuBuilderEvent;
use Sylius\Component\Core\Model\OrderInterface;

final class AdminOrderShowMenuListener
{
    public function __construct(
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private readonly StateMachineInterface $stateMachine,
    ) {
    }

    public function addRefundButton(OrderShowMenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $order = $event->getOrder();

        if ($order->getState() !== OrderInterface::STATE_FULFILLED) {
            return;
        }

        $payment = $order->getLastPayment();
        if (
            null === $payment ||
            !$this->adyenPaymentMethodChecker->isAdyenPayment($payment) ||
            !$this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::AUTOMATIC) ||
            !$this->stateMachine->can($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REVERSE)
        ) {
            return;
        }

        $menu
            ->addChild('reverse_payment', [
                'route' => 'sylius_adyen_admin_order_payment_reverse',
                'routeParameters' => [
                    'id' => $order->getId(),
                    'paymentId' => $payment->getId(),
                ],
            ])
            ->setLabel('sylius.ui.refund')
            ->setLabelAttribute('icon', 'reply')
            ->setLabelAttribute('color', 'purple')
        ;
    }
}
