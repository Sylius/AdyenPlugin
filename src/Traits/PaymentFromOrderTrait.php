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
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Webmozart\Assert\Assert;

trait PaymentFromOrderTrait
{
    private function getMethod(PaymentInterface $payment): PaymentMethodInterface
    {
        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        return $method;
    }

    private function getPayment(OrderInterface $order, ?string $state = null): PaymentInterface
    {
        $payment = $order->getLastPayment($state);

        if (null === $payment) {
            throw new \InvalidArgumentException(
                sprintf('No payment associated with Order #%d', (int) $order->getId()),
            );
        }

        return $payment;
    }
}
