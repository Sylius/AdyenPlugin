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

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;

interface AdyenPaymentMethodCheckerInterface
{
    public function isAdyenPayment(PaymentInterface $payment): bool;

    public function isAdyenPaymentMethod(PaymentMethodInterface $paymentMethod): bool;

    public function isCaptureMode(PaymentInterface|PaymentMethodInterface $payment, string $mode): bool;
}
