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

trait OrderFromPaymentTrait
{
    private function getOrderFromPayment(PaymentInterface $payment): OrderInterface
    {
        $result = $payment->getOrder();
        if (null === $result) {
            throw new \InvalidArgumentException(sprintf('Payment #%d has no order', (int) $payment->getId()));
        }

        return $result;
    }
}
