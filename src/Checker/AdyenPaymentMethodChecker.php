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
}
