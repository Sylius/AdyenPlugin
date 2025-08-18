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

namespace Sylius\AdyenPlugin\Processor\State;

use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentAuthorizeStateProcessorInterface
{
    public function process(PaymentInterface $payment): void;
}
