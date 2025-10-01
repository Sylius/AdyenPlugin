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

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

final class OrderPaymentProcessor implements OrderProcessorInterface
{
    public function __construct(
        private OrderProcessorInterface $decorated,
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function process(BaseOrderInterface $order): void
    {
        /** @var OrderInterface $order */
        $payment = $order->getLastPayment();
        if (
            $payment?->getState() === PaymentInterface::STATE_NEW &&
            $this->adyenPaymentMethodChecker->isAdyenPayment($payment)
        ) {
            return;
        }

        $this->decorated->process($order);
    }
}
