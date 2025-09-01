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

use Adyen\Model\Checkout\PaymentMethod as AdyenPaymentMethod;
use Adyen\Model\Checkout\StoredPaymentMethod as AdyenStoredPaymentMethod;
use Sylius\AdyenPlugin\Model\AvailablePaymentMethod;
use Sylius\AdyenPlugin\Model\StoredPaymentMethod;

final class AdyenPaymentMethodsMapper implements PaymentMethodsMapperInterface
{
    public function mapAvailable(array $adyenPaymentMethods): array
    {
        $result = [];
        /** @var AdyenPaymentMethod $method */
        foreach ($adyenPaymentMethods as $method) {
            $type = $method->getType();
            if ($type === null || $type === '') {
                continue;
            }

            $result[] = new AvailablePaymentMethod(
                type: $type,
                name: $method->getName(),
                brands: $method->getBrands() ?? [],
                configuration: $method->getConfiguration() ?? null,
                issuers: $method->getIssuers() ?? null,
            );
        }

        return $result;
    }

    public function mapStored(array $adyenStored): array
    {
        $result = [];
        /** @var AdyenStoredPaymentMethod $method */
        foreach ($adyenStored as $method) {
            $id = $method->getId();
            $type = $method->getType();
            if ($id === null || $type === null) {
                continue;
            }

            $result[] = new StoredPaymentMethod(
                id: $id,
                type: $type,
                supportedShopperInteractions: $method->getSupportedShopperInteractions() ?? [],
                brand: $method->getBrand(),
                lastFour: $method->getLastFour(),
                expiryMonth: $method->getExpiryMonth(),
                expiryYear: $method->getExpiryYear(),
                holderName: $method->getHolderName(),
            );
        }

        return $result;
    }
}
