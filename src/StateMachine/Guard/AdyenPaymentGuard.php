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

namespace Sylius\AdyenPlugin\StateMachine\Guard;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Component\Core\Model\PaymentInterface;

final class AdyenPaymentGuard
{
    public function __construct(
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function canBeCompleted(PaymentInterface $payment): bool
    {
        if ($this->adyenPaymentMethodChecker->isAdyenPayment($payment)) {
            return $this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::MANUAL);
        }

        return true;
    }
}
