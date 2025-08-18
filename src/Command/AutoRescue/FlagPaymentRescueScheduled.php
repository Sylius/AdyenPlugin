<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Command\AutoRescue;

use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\Component\Core\Model\PaymentInterface;

// TODO: Remove implementation of PaymentLifecycleCommand interface
final class FlagPaymentRescueScheduled implements PaymentLifecycleCommand
{
    public function __construct(
        public readonly mixed $paymentId,
        public readonly string $merchantReference,
        public readonly string $pspReference,
        public readonly string $rescueReference,
    ) {
    }

    public function getPayment(): PaymentInterface
    {
        throw new \LogicException('TODO: Remove getPayment() method in Interface PaymentLifecycleCommand. to make commands stateless');
    }
}
