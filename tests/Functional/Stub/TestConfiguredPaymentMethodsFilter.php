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

namespace Tests\Sylius\AdyenPlugin\Functional\Stub;

use Sylius\AdyenPlugin\Filter\ConfiguredPaymentMethodsFilter;
use Sylius\AdyenPlugin\Filter\PaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Filter\PaymentMethodsMode;
use Sylius\AdyenPlugin\Model\AvailablePaymentMethod;

final class TestConfiguredPaymentMethodsFilter implements PaymentMethodsFilterInterface
{
    /** @var string[] */
    private array $allowed = [];

    private PaymentMethodsMode $mode = PaymentMethodsMode::CONFIG;

    /** @param string[] $allowedTypes */
    public function setConfig(array $allowedTypes, PaymentMethodsMode $mode): void
    {
        $this->allowed = $allowedTypes;
        $this->mode = $mode;
    }

    /** @param AvailablePaymentMethod[] $paymentMethods */
    public function filter(array $paymentMethods): array
    {
        return (new ConfiguredPaymentMethodsFilter($this->allowed, $this->mode))->filter($paymentMethods);
    }
}
