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

namespace Sylius\AdyenPlugin\Bus\Command;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

final class RefundPayment implements PaymentLifecycleCommand
{
    public function __construct(private readonly RefundPaymentInterface $refundPayment)
    {
    }

    public function getRefundPayment(): RefundPaymentInterface
    {
        return $this->refundPayment;
    }

    public function getPayment(): PaymentInterface
    {
        throw new \LogicException('TODO: Remove getPayment() method in Interface PaymentLifecycleCommand. to make commands stateless');

    }
}
