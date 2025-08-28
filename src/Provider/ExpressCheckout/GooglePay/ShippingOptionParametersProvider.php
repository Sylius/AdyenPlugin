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

namespace Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay;

use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

final class ShippingOptionParametersProvider implements ShippingOptionParametersProviderInterface
{
    public function __construct(
        private readonly ShippingMethodsResolverInterface $shippingMethodsResolver,
        private readonly ServiceRegistryInterface $calculators,
        private readonly MoneyFormatterInterface $moneyFormatter,
    ) {
    }

    public function provide(OrderInterface $order): array
    {
        if (!$order->isShippingRequired()) {
            return [];
        }

        $shipment = $order->getShipments()->first();
        $shippingMethods = $this->shippingMethodsResolver->getSupportedMethods($shipment);

        $shippingOptions = [];

        foreach ($shippingMethods as $shippingMethod) {
            /** @var CalculatorInterface $calculator */
            $calculator = $this->calculators->get($shippingMethod->getCalculator());
            $fee = $calculator->calculate($shipment, $shippingMethod->getConfiguration());

            $shippingOptions[] = [
                'id' => $shippingMethod->getCode(),
                'label' => sprintf('%s (%s)', $shippingMethod->getName(), $this->moneyFormatter->format($fee, $order->getCurrencyCode())),
                'description' => $shippingMethod->getDescription(),
            ];
        }

        return [
            'shippingOptions' => $shippingOptions,
            'defaultSelectedOptionId' => $shipment->getMethod()->getCode(),
        ];
    }
}
