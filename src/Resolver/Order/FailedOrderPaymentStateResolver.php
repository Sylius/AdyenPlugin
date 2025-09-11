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

namespace Sylius\AdyenPlugin\Resolver\Order;

use Doctrine\Common\Collections\Collection;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Webmozart\Assert\Assert;

final class FailedOrderPaymentStateResolver implements StateResolverInterface
{
    public function __construct(
        private StateMachineInterface $stateMachine,
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function resolve(BaseOrderInterface $order): void
    {
        /** @var OrderInterface $order */
        Assert::isInstanceOf($order, OrderInterface::class);

        $failedPayments = $this->getPaymentsWithState($order, PaymentInterface::STATE_FAILED);
        if ($failedPayments->isEmpty()) {
            return;
        }

        $newPayments = $this->getPaymentsWithState($order, PaymentInterface::STATE_NEW, true);
        if ($newPayments->isEmpty()) {
            return;
        }

        if ($this->stateMachine->can($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REQUEST_PAYMENT)) {
            $this->stateMachine->apply($order, OrderPaymentTransitions::GRAPH, OrderPaymentTransitions::TRANSITION_REQUEST_PAYMENT);
        }
    }

    private function getPaymentsWithState(OrderInterface $order, string $state, bool $onlyAdyen = false): Collection
    {
        /** @var Collection<array-key, PaymentInterface> $payments */
        $payments = $order->getPayments()->filter(function (BasePaymentInterface $payment) use ($state) {
            return $state === $payment->getState();
        });

        if ($onlyAdyen) {
            $payments = $payments->filter(function (BasePaymentInterface $payment) {
                return $this->adyenPaymentMethodChecker->isAdyenPayment($payment);
            });
        }

        return $payments;
    }
}
