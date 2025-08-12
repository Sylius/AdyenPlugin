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

use Sylius\Component\Core\Model\PaymentInterface;

final class EsdCardPaymentSupportChecker implements EsdCardPaymentSupportCheckerInterface
{
    /** @param string[] $supportedCardBrands */
    public function __construct(
        private readonly array $supportedCardBrands,
    ) {
    }

    public function isSupported(array $payload, ?PaymentInterface $payment = null): bool
    {
        // For submit payments - check payload
        if (isset($payload['paymentMethod'])) {
            $paymentMethodType = $payload['paymentMethod']['type'] ?? '';
            $cardBrand = $payload['paymentMethod']['brand'] ?? '';

            if ($paymentMethodType !== 'scheme') {
                return false;
            }

            return in_array($cardBrand, $this->supportedCardBrands, true);
        }

        // For capture payments - check payment details
        if ($payment !== null) {
            $paymentDetails = $payment->getDetails();
            $cardBrand = $paymentDetails['additionalData']['cardBin'] ?? $paymentDetails['paymentMethod']['brand'] ?? '';

            return in_array($cardBrand, $this->supportedCardBrands, true);
        }

        return false;
    }
}
