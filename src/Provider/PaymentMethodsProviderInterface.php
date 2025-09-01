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

use Sylius\AdyenPlugin\Model\AvailablePaymentMethod;
use Sylius\AdyenPlugin\Model\StoredPaymentMethod;
use Sylius\Component\Core\Model\OrderInterface;

interface PaymentMethodsProviderInterface
{
    /**
     * @return array{
     *   paymentMethods: AvailablePaymentMethod[],
     *   storedPaymentMethods: StoredPaymentMethod[]
     * }
     */
    public function provideForOrder(string $paymentMethodCode, OrderInterface $order): array;
}
