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

namespace Sylius\AdyenPlugin\Filter;

use Sylius\AdyenPlugin\Model\PaymentMethod;

final class ManualCapturePaymentMethodsFilter implements PaymentMethodsFilterInterface
{
    public function __construct(
        private readonly array $manualCaptureSupportingTypes = [],
    ) {
    }

    public function filter(array $paymentMethods, array $context = []): array
    {
        if (false === ($context['manual_capture'] ?? false)) {
            return $paymentMethods;
        }

        return array_values(array_filter($paymentMethods, function (PaymentMethod $paymentMethod): bool {
            return in_array($paymentMethod->type, $this->manualCaptureSupportingTypes, true);
        }));
    }
}
