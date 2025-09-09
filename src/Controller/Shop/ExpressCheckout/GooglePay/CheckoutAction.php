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

use Sylius\AdyenPlugin\Checker\OrderCheckoutCompleteIntegrityChecker;
use Sylius\AdyenPlugin\Exception\CheckoutValidationException;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\GooglePay\OrderAddressModifierInterface;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\OrderCustomerModifierInterface;
use Sylius\AdyenPlugin\Resolver\ExpressCheckout\CheckoutResolverInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class CheckoutAction
{
    public function __construct(
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly OrderAddressModifierInterface $orderAddressModifier,
        private readonly OrderCustomerModifierInterface $orderCustomerModifier,
        private readonly CheckoutResolverInterface $checkoutResolver,
        private readonly OrderCheckoutCompleteIntegrityChecker $orderCheckoutCompleteIntegrityChecker,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $order = $this->paymentCheckoutOrderResolver->resolve();
            $this->orderCheckoutCompleteIntegrityChecker->check($order);

            $data = json_decode($request->getContent(), true);
            Assert::isArray($data);

            $email = $data['email'] ?? null;
            $newAddress = $data['shippingAddress'] ?? null;

            if (!isset($email, $newAddress)) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Missing required parameters: email or shippingAddress.',
                ], 400);
            }

            $this->orderAddressModifier->modify($order, $newAddress);
            $this->orderCustomerModifier->modify($order, $email);

            $this->checkoutResolver->resolve($order);

            return new JsonResponse(['orderToken' => $order->getTokenValue()]);
        } catch (CheckoutValidationException $exception) {
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $exception) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Order cannot be checked out.',
                'code' => $exception->getCode(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
