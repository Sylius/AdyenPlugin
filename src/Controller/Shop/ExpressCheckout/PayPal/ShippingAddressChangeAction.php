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

namespace Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\PayPal;

use Doctrine\Persistence\ObjectManager;
use Sylius\AdyenPlugin\Bus\Command\CreatePaymentDetailForPayment;
use Sylius\AdyenPlugin\Exception\PaypalNoShippingMethodsAvailableException;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\Paypal\OrderAddressModifierInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

final class ShippingAddressChangeAction
{
    public function __construct(
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly ObjectManager $orderManager,
        private readonly OrderAddressModifierInterface $orderAddressModifier,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $paymentData = $data['paymentData'] ?? null;
        $pspReference = $data['pspReference'] ?? null;
        $newAddress = $data['shippingAddress'] ?? null;

        if (!isset($paymentData, $pspReference, $newAddress)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Missing required parameters: paymentData, pspReference, or shippingAddress.',
            ], 400);
        }

        try {
            $this->orderAddressModifier->modifyTemporaryAddress($order, $newAddress);
            $this->orderProcessor->process($order);
            $this->orderManager->flush();

            $this->messageBus->dispatch(new CreatePaymentDetailForPayment($order->getLastPayment()));

            $client = $this->adyenClientProvider->getDefaultClient();
            $paypalUpdateOrderResponse = $client->updatesOrderForPaypalExpressCheckout(
                $pspReference,
                $paymentData,
                $order,
            );

            return new JsonResponse($paypalUpdateOrderResponse->toArray());
        } catch (PaypalNoShippingMethodsAvailableException) {
            return new JsonResponse([
                'error' => true,
                'code' => 'NO_SHIPPING_OPTION',
            ], 400);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Order could not be processed',
                'code' => $exception->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
