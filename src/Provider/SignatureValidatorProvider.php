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

namespace Sylius\AdyenPlugin\Provider;

use Sylius\AdyenPlugin\Client\SignatureValidator;
use Sylius\AdyenPlugin\Exception\AdyenNotConfiguredException;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;

final class SignatureValidatorProvider implements SignatureValidatorProviderInterface
{
    use GatewayConfigFromPaymentTrait;

    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function getValidatorForCode(string $code): SignatureValidator
    {
        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($code);

        if (null === $paymentMethod) {
            throw new AdyenNotConfiguredException($code);
        }
        $gatewayConfig = $this->getGatewayConfig($paymentMethod);

        return new SignatureValidator(
            (string) $gatewayConfig->getConfig()['hmacKey'],
        );
    }
}
