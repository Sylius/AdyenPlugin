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

use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class AdyenPaymentDetailFactory implements AdyenPaymentDetailFactoryInterface
{
    public function __construct(private readonly FactoryInterface $adyenPaymentDetailFactory)
    {
    }

    public function createForPayment(PaymentInterface $payment): AdyenPaymentDetailInterface
    {
        $result = $this->createNew();
        $result->setPayment($payment);
        $result->setAmount($payment->getAmount());

        return $result;
    }

    public function createNew(): AdyenPaymentDetailInterface
    {
        /** @var AdyenPaymentDetailInterface $paymentDetail */
        $paymentDetail = $this->adyenPaymentDetailFactory->createNew();

        return $paymentDetail;
    }
}
