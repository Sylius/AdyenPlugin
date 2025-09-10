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

namespace Sylius\AdyenPlugin\Traits;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

trait PayableOrderPaymentTrait
{
    public function getPayablePayment(OrderInterface $order): PaymentInterface
    {
        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW) ?? $order->getLastPayment(PaymentInterface::STATE_CART);

        if (null === $payment) {
            throw new \InvalidArgumentException(
                sprintf('Order #%d has no Payment associated', (int) $order->getId()),
            );
        }

        return $payment;
    }
}
