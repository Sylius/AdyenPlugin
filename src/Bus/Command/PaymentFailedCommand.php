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

use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentFailedCommand implements PaymentFinalizationCommand
{
    public function __construct(private readonly PaymentInterface $payment)
    {
    }

    public function getPaymentTransition(): string
    {
        return PaymentGraph::TRANSITION_FAIL;
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }
}
