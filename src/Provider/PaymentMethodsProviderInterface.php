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

namespace Sylius\AdyenPlugin\Provider;

use Sylius\AdyenPlugin\Model\PaymentMethodData;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PaymentMethodsProviderInterface
{
    public function provideForOrder(PaymentMethodInterface $adyenPaymentMethod, OrderInterface $order): PaymentMethodData;
}
