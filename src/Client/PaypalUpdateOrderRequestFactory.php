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

namespace Sylius\AdyenPlugin\Client;

use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\DeliveryMethod;
use Adyen\Model\Checkout\PaypalUpdateOrderRequest;
use Adyen\Model\Checkout\TaxTotal;
use Sylius\AdyenPlugin\Exception\PaypalNoShippingMethodsAvailableException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

final class PaypalUpdateOrderRequestFactory implements PaypalUpdateOrderRequestFactoryInterface
{
    public function __construct(
        private readonly ShippingMethodsResolverInterface $shippingMethodsResolver,
        private readonly ServiceRegistryInterface $calculators,
    ) {
    }

    public function create(
        string $pspReference,
        string $paymentData,
        OrderInterface $order,
    ): PaypalUpdateOrderRequest {
        $request = new PaypalUpdateOrderRequest();
        $request->setPspReference($pspReference);
        $request->setPaymentData($paymentData);
        $request->setTaxTotal(new TaxTotal([
            'amount' => new Amount([
                'currency' => $order->getCurrencyCode(),
                'value' => $order->getTaxExcludedTotal(),
            ]),
        ]));

        $request->setAmount(new Amount([
            'currency' => $order->getCurrencyCode(),
            'value' => $order->getTotal(),
        ]));

        if ($order->isShippingRequired()) {
            $shipment = $order->getShipments()->first();
            $shippingMethods = $this->shippingMethodsResolver->getSupportedMethods($shipment);

            if (0 === count($shippingMethods)) {
                throw new PaypalNoShippingMethodsAvailableException();
            }

            $deliveryMethods = [];
            foreach ($shippingMethods as $shippingMethod) {
                /** @var CalculatorInterface $calculator */
                $calculator = $this->calculators->get($shippingMethod->getCalculator());
                $fee = $calculator->calculate($shipment, $shippingMethod->getConfiguration());

                $deliveryMethod = new DeliveryMethod([
                    'type' => 'Shipping',
                    'reference' => $shippingMethod->getCode(),
                    'description' => $shippingMethod->getName(),
                    'amount' => new Amount([
                        'currency' => $order->getCurrencyCode(),
                        'value' => $fee,
                    ]),
                    'selected' => $shipment->getMethod() === $shippingMethod,
                ]);

                $deliveryMethods[] = $deliveryMethod;
            }

            $request->setDeliveryMethods($deliveryMethods);
        }

        return $request;
    }
}
