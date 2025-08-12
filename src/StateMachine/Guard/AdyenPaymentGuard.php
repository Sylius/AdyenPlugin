<?php

declare(strict_types=1);

namespace Sylius\AdyenPlugin\StateMachine\Guard;

use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class AdyenPaymentGuard
{
    public function canBeCompleted(PaymentInterface $payment): bool
    {
        /** @var PaymentMethodInterface|null $method */
        $method = $payment->getMethod();
        $gatewayConfig = $method?->getGatewayConfig();
        if (null === $gatewayConfig) {
            return false;
        }

        $factoryName = $gatewayConfig->getConfig()['factory_name'] ?? $gatewayConfig->getFactoryName();

        return $factoryName !== AdyenClientProviderInterface::FACTORY_NAME;
    }
}
