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

namespace Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\ApplePay;

use Adyen\AdyenException;
use Doctrine\Persistence\ObjectManager;
use Sylius\AdyenPlugin\Bus\Command\CreatePaymentDetailForPayment;
use Sylius\AdyenPlugin\Exception\PaypalNoShippingMethodsAvailableException;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\Paypal\OrderAddressModifierInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class ShippingAddressChangeAction
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly ObjectManager $orderManager,
        private readonly ShippingMethodsResolverInterface $shippingMethodsResolver,
        private readonly ServiceRegistryInterface $calculators,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var OrderInterface $order */
        $order = $this->cartContext->getCart();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $newAddress = $data['shippingContact'] ?? null;

        if (!isset($newAddress)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Missing required parameter: shippingContact.',
            ], 400);
        }

        $address = new Address();
        $address->setFirstName('temp');
        $address->setLastName('temp');
        $address->setStreet('temp');
        $address->setCountryCode($newAddress['countryCode']);
        $address->setCity($newAddress['locality']);
        $address->setPostcode($newAddress['postalCode']);
        $address->setProvinceName($newAddress['administrativeArea']);

        $order->setBillingAddress($address);
        $order->setShippingAddress(clone $address);

        $this->orderProcessor->process($order);
        $this->orderManager->flush();

        $shipment = $order->getShipments()->first();
        $shippingMethods = $this->shippingMethodsResolver->getSupportedMethods($shipment);

        $shippingOptions = [];

        foreach ($shippingMethods as $shippingMethod) {
            /** @var CalculatorInterface $calculator */
            $calculator = $this->calculators->get($shippingMethod->getCalculator());
            $fee = $calculator->calculate($shipment, $shippingMethod->getConfiguration());

            $shippingOptions[] = [
                'identifier' => $shippingMethod->getCode(),
                'label' => $shippingMethod->getName(),
                'amount' => number_format($fee / 100, 2, '.', ''),
                'detail' => $shippingMethod->getDescription(),
                'selected' => $shipment->getMethod()->getCode() === $shippingMethod->getCode(),
            ];
        }

        return new JsonResponse([
            'newTotal' => [
                'label' => 'Total',
                'amount' => number_format($order->getTotal() / 100, 2, '.', ''),
            ],
            'newLineItems' => [
                [
                    'label' => 'Items',
                    'amount' => number_format($order->getItemsSubtotal() / 100, 2, '.', ''),
                    'type' => 'final',
                ],
                [
                    'label' => 'Shipping',
                    'amount' => number_format($order->getShippingTotal() / 100, 2, '.', ''),
                    'type' => 'final',
                ],
                [
                    'label' => 'Discount',
                    'amount' => number_format($order->getOrderPromotionTotal() / 100, 2, '.', ''),
                    'type' => 'final',
                ],
                [
                    'label' => 'Tax',
                    'amount' => number_format($order->getTaxExcludedTotal() / 100, 2, '.', ''),
                    'type' => 'final',
                ],
            ],
            'newShippingMethods' => $shippingOptions,
        ]);
    }
}
