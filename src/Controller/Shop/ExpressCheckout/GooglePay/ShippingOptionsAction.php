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

namespace Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\GooglePay;

use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\AddressProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\TransactionInfoProviderInterface;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Registry\ServiceRegistryInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class ShippingOptionsAction
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly ShippingMethodsResolverInterface $shippingMethodsResolver,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly AddressProviderInterface $addressProvider,
        private readonly ServiceRegistryInterface $calculators,
        private readonly MoneyFormatterInterface $moneyFormatter,
        private readonly TransactionInfoProviderInterface $transactionInfoProvider,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var OrderInterface $order */
        $order = $this->cartContext->getCart();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $shippingAddressData = $data['shippingAddress'] ?? null;
        $shippingOptionId = $data['shippingOptionId'] ?? null;

        $address = $this->addressProvider->createTemporaryAddress($shippingAddressData);
        $order->setBillingAddress($address);
        $order->setShippingAddress($address);

        $this->orderProcessor->process($order);
        $shipment = $order->getShipments()->first();
        $shippingMethods = $this->shippingMethodsResolver->getSupportedMethods($shipment);

        $shippingOptions = [];

        foreach ($shippingMethods as $shippingMethod) {
            $optionId = (string) $shippingMethod->getCode();

            /** @var CalculatorInterface $calculator */
            $calculator = $this->calculators->get($shippingMethod->getCalculator());
            $fee = $calculator->calculate($shipment, $shippingMethod->getConfiguration());

            $shippingOptions[] = [
                'id' => $optionId,
                'label' => sprintf('%s (%s)', $shippingMethod->getName(), $this->moneyFormatter->format($fee, $order->getCurrencyCode())),
                'description' => $shippingMethod->getDescription(),
            ];

            if ($shippingOptionId === $optionId && $shipment->getMethod()->getCode() !== $optionId) {
                $shipment->setMethod($shippingMethod);
                $this->orderProcessor->process($order);
            }
        }

        return new JsonResponse([
            'shippingOptionParameters' => [
                'shippingOptions' => $shippingOptions,
                'defaultSelectedOptionId' => $shipment->getMethod()->getCode(),
            ],
            'transactionInfo' => $this->transactionInfoProvider->provide($order),
        ]);
    }
}
