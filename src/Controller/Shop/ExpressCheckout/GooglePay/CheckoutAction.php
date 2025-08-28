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
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\GooglePay\OrderAddressModifierInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\CustomerProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class CheckoutAction
{
    public function __construct(
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly ObjectManager $orderManager,
        private readonly OrderAddressModifierInterface $orderAddressModifier,
        private readonly CustomerProviderInterface $customerProvider,
        private readonly StateMachineInterface $stateMachine,
        private readonly ShippingMethodRepositoryInterface $shippingMethodRepository,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $email = $data['email'] ?? null;
        $newAddress = $data['shippingAddress'] ?? null;
        $shippingOptionId = $data['shippingOptionId'] ?? null;

        if (!isset($email, $newAddress)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Missing required parameters: email or shippingAddress.',
            ], 400);
        }

        $this->orderAddressModifier->modify($order, $newAddress);

        $customer = $order->getCustomer();
        if ($customer === null) {
            $customer = $this->customerProvider->getOrCreateCustomer($email, $order->getBillingAddress());
            $order->setCustomer($customer);
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);

        if ($order->isShippingRequired()) {
            $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $shippingOptionId]);
            $order->getShipments()->first()->setMethod($shippingMethod);
            $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        }

        $paymentMethod = $this->paymentMethodRepository->findOneByChannel($order->getChannel());
        $order->getLastPayment(PaymentInterface::STATE_CART)->setMethod($paymentMethod);
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        $this->orderManager->flush();

        return new JsonResponse(['order_id' => $order->getId()]);
    }
}
