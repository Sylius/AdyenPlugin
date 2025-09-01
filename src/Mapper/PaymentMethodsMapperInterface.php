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

namespace Sylius\AdyenPlugin\Mapper;

use Sylius\AdyenPlugin\Model\AvailablePaymentMethod;
use Sylius\AdyenPlugin\Model\StoredPaymentMethod;

interface PaymentMethodsMapperInterface
{
    /**
     * @param array<int,object> $adyenPaymentMethods
     *
     * @return AvailablePaymentMethod[]
     */
    public function mapAvailable(array $adyenPaymentMethods): array;

    /**
     * @param array<int,object> $adyenStored
     *
     * @return StoredPaymentMethod[]
     */
    public function mapStored(array $adyenStored): array;
}
