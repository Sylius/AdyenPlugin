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

use Doctrine\Persistence\ObjectManager;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\GooglePay\OrderAddressModifierInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\ShippingOptionParametersProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\TransactionInfoProviderInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class ShippingOptionsAction
{
    public function __construct(
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly ObjectManager $orderManager,
        private readonly ShippingMethodRepositoryInterface $shippingMethodsRepository,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly OrderAddressModifierInterface $orderAddressModifier,
        private readonly TransactionInfoProviderInterface $transactionInfoProvider,
        private readonly ShippingOptionParametersProviderInterface $shippingOptionParametersProvider,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $newAddress = $data['shippingAddress'] ?? null;
        $shippingOptionId = $data['shippingOptionId'] ?? null;

        if (!isset($newAddress)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Missing required parameter: shippingAddress.',
            ], 400);
        }

        try {
            $this->orderAddressModifier->modifyTemporaryAddress($order, $newAddress);
            $this->orderProcessor->process($order);

            if ($order->isShippingRequired() && $shippingOptionId !== 'shipping_option_unselected') {
                $shipment = $order->getShipments()->first();
                $shippingMethod = $this->shippingMethodsRepository->findOneBy(['code' => $shippingOptionId]);
                Assert::notNull($shippingMethod);

                $shipment->setMethod($shippingMethod);
                $this->orderProcessor->process($order);
            }

            $this->orderManager->flush();

            return new JsonResponse([
                'shippingOptionParameters' => $this->shippingOptionParametersProvider->provide($order),
                'transactionInfo' => $this->transactionInfoProvider->provide($order),
            ]);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Order could not be processed.',
                'code' => $exception->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
