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

use Doctrine\Persistence\ObjectManager;
use Sylius\AdyenPlugin\Exception\NoShippingMethodsAvailableException;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\ShippingMethodsProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\TransactionInfoProviderInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class ShippingOptionsChangeAction
{
    /** @param ShippingMethodRepositoryInterface<ShippingMethodInterface> $shippingMethodRepository */
    public function __construct(
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly ObjectManager $orderManager,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly ShippingMethodRepositoryInterface $shippingMethodRepository,
        private readonly TransactionInfoProviderInterface $transactionInfoProvider,
        private readonly ShippingMethodsProviderInterface $shippingMethodsProvider,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $selectedShippingMethod = $data['selectedShippingMethod'] ?? null;

        if (!isset($selectedShippingMethod) || $selectedShippingMethod === '') {
            return new JsonResponse(
                array_merge(
                    [
                    'error' => true,
                    'code' => 'NO_SHIPPING_OPTION',
                    'message' => 'Missing or invalid selectedShippingMethod.',
                ],
                    $this->transactionInfoProvider->provide($order),
                    $this->shippingMethodsProvider->provide($order),
                ),
            );
        }

        try {
            $shipment = $order->getShipments()->first();
            $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $selectedShippingMethod]);
            if (null === $shippingMethod) {
                return new JsonResponse(
                    array_merge(
                        [
                            'error' => true,
                            'code' => 'NO_SHIPPING_OPTION',
                            'message' => 'Selected shipping method does not exist.',
                        ],
                        $this->transactionInfoProvider->provide($order),
                        $this->shippingMethodsProvider->provide($order),
                    ),
                );
            }

            $shipment->setMethod($shippingMethod);
            $this->orderProcessor->process($order);
            $this->orderManager->flush();

            return new JsonResponse(
                array_merge(
                    $this->transactionInfoProvider->provide($order),
                    $this->shippingMethodsProvider->provide($order),
                ),
            );
        } catch (NoShippingMethodsAvailableException $exception) {
            return new JsonResponse([
                'error' => true,
                'code' => 'NO_SHIPPING_OPTION',
                'message' => $exception->getMessage(),
            ], 400);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Order could not be processed.',
                'code' => $exception->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
