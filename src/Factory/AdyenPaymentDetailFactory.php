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
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
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

        $result->setCaptureMode(
            $this->getGatewayConfig($payment)['captureMode'] ??
            PaymentCaptureMode::AUTOMATIC,
        );

        return $result;
    }

    public function createNew(): AdyenPaymentDetailInterface
    {
        /** @var AdyenPaymentDetailInterface $paymentDetail */
        $paymentDetail = $this->adyenPaymentDetailFactory->createNew();

        return $paymentDetail;
    }

    private function getGatewayConfig(PaymentInterface $payment): array
    {
        /** @var PaymentMethodInterface|null $paymentMethod */
        $paymentMethod = $payment->getMethod();
        /** @var GatewayConfigInterface|null $gatewayConfig */
        $gatewayConfig = $paymentMethod?->getGatewayConfig();

        return $gatewayConfig?->getConfig() ?? [];
    }
}
