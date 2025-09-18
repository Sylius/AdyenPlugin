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

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class AdyenPaymentDetailFactory implements AdyenPaymentDetailFactoryInterface
{
    public function __construct(
        private readonly FactoryInterface $adyenPaymentDetailFactory,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function createForPayment(PaymentInterface $payment): AdyenPaymentDetailInterface
    {
        $paymentDetail = $this->createNew();
        $paymentDetail->setPayment($payment);
        $paymentDetail->setAmount($payment->getAmount());
        $paymentDetail->setCaptureMode($this->getCaptureMode($payment));

        return $paymentDetail;
    }

    public function createNew(): AdyenPaymentDetailInterface
    {
        /** @var AdyenPaymentDetailInterface $paymentDetail */
        $paymentDetail = $this->adyenPaymentDetailFactory->createNew();

        return $paymentDetail;
    }

    private function getCaptureMode(
        PaymentInterface $payment,
    ): string {
        return $this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::MANUAL)
            ? PaymentCaptureMode::MANUAL
            : PaymentCaptureMode::AUTOMATIC
        ;
    }
}
