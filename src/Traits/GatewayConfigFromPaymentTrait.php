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

use Sylius\AdyenPlugin\Exception\AdyenNotConfiguredException;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;

trait GatewayConfigFromPaymentTrait
{
    private function getGatewayConfig(PaymentMethodInterface $paymentMethod): GatewayConfigInterface
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            throw new AdyenNotConfiguredException((string) $paymentMethod->getCode());
        }

        return $gatewayConfig;
    }
}
