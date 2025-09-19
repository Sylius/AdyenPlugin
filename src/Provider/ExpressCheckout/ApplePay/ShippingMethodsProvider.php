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

namespace Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay;

use Sylius\AdyenPlugin\Exception\NoShippingMethodsAvailableException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

final class ShippingMethodsProvider implements ShippingMethodsProviderInterface
{
    public function __construct(
        private readonly ShippingMethodsResolverInterface $shippingMethodsResolver,
        private readonly ServiceRegistryInterface $calculators,
    ) {
    }

    public function provide(OrderInterface $order): array
    {
        if (!$order->isShippingRequired()) {
            return [];
        }

        $shipment = $order->getShipments()->first();
        $shippingMethods = $this->shippingMethodsResolver->getSupportedMethods($shipment);

        if (0 === count($shippingMethods)) {
            throw new NoShippingMethodsAvailableException();
        }

        $shippingOptions = [];

        foreach ($shippingMethods as $shippingMethod) {
            /** @var CalculatorInterface $calculator */
            $calculator = $this->calculators->get($shippingMethod->getCalculator());
            $fee = $calculator->calculate($shipment, $shippingMethod->getConfiguration());

            $shippingOptions[] = [
                'identifier' => $shippingMethod->getCode(),
                'label' => $shippingMethod->getName(),
                'amount' => $this->formatPrice($fee),
                'detail' => $shippingMethod->getDescription() ?? '',
            ];
        }

        return ['newShippingMethods' => $shippingOptions];
    }

    private function formatPrice(int $price): string
    {
        return number_format($price / 100, 2, '.', '');
    }
}
