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

use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

final class RefundPayment
{
    public function __construct(private RefundPaymentInterface $refundPayment)
    {
    }

    public function getRefundPayment(): RefundPaymentInterface
    {
        return $this->refundPayment;
    }
}
