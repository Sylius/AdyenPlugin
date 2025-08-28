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

namespace Sylius\AdyenPlugin\Checker;

use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

final class AdyenPaymentMethodChecker implements AdyenPaymentMethodCheckerInterface
{
    public function isAdyenPayment(PaymentInterface $payment): bool
    {
        /** @var PaymentMethodInterface|null $method */
        $method = $payment->getMethod();
        if (null === $method) {
            return false;
        }

        return $this->isAdyenPaymentMethod($method);
    }

    public function isAdyenPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            return false;
        }

        $factoryName = $gatewayConfig->getConfig()['factory_name'] ?? $gatewayConfig->getFactoryName();

        return $factoryName === AdyenClientProviderInterface::FACTORY_NAME;
    }

    public function isCaptureMode(PaymentInterface|PaymentMethodInterface $payment, string $mode): bool
    {
        if ($payment instanceof PaymentInterface) {
            $payment = $payment->getMethod();
            if (null === $payment) {
                throw new \InvalidArgumentException('The payment has no payment method assigned.');
            }
        }

        $gatewayConfig = $payment->getGatewayConfig();

        // TODO: temp for testing purposes, remove when manual capture mode is added to the gateway config //
        return $mode === PaymentCaptureMode::MANUAL;

        return $mode === ($gatewayConfig?->getConfig()['capture_mode'] ?? null);
    }
}
