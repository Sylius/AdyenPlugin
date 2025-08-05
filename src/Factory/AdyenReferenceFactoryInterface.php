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

namespace Sylius\AdyenPlugin\Factory;

use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

interface AdyenReferenceFactoryInterface extends FactoryInterface
{
    public function createForPayment(PaymentInterface $payment): AdyenReferenceInterface;

    public function createForRefund(
        string $reference,
        PaymentInterface $payment,
        RefundPaymentInterface $refundPayment,
    ): AdyenReferenceInterface;
}
