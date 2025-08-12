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

namespace Sylius\AdyenPlugin\Collector;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

interface CompositeEsdCollectorInterface
{
    public function collect(
        OrderInterface $order,
        array $gatewayConfig,
        array $payload,
        ?PaymentInterface $payment = null,
    ): array;

    public function shouldIncludeEsd(
        OrderInterface $order,
        array $gatewayConfig,
        array $payload,
        ?PaymentInterface $payment = null,
    ): bool;
}
