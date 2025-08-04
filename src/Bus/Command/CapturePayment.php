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

namespace Sylius\AdyenPlugin\Bus\Command;

use Sylius\AdyenPlugin\PaymentTransitions;
use Sylius\Component\Core\Model\PaymentInterface;

final class CapturePayment implements PaymentFinalizationCommand
{
    /** @var PaymentInterface */
    private $payment;

    public function __construct(PaymentInterface $payment)
    {
        $this->payment = $payment;
    }

    public function getPaymentTransition(): string
    {
        return PaymentTransitions::TRANSITION_CAPTURE;
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }
}
