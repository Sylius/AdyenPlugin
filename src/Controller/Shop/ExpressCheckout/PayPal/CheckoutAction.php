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
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\Paypal\OrderAddressModifierInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\CustomerProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

class CheckoutAction
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly ObjectManager $orderManager,
        private readonly CustomerProviderInterface $customerProvider,
        private readonly StateMachineInterface $stateMachine,
        private readonly OrderAddressModifierInterface $orderAddressModifier,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var OrderInterface $order */
        $order = $this->cartContext->getCart();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $newBillingAddress = $data['billingAddress'] ?? null;
        $newShippingAddress = $data['deliveryAddress'] ?? null;
        $payer = $data['payer'] ?? null;

        if (!$newBillingAddress || !$newShippingAddress || !$payer) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Missing required parameters: billingAddress, deliveryAddress, or payer.',
            ], 400);
        }

        $this->orderAddressModifier->modify($order, $newBillingAddress, $newShippingAddress, $payer);

        $email = $payer['email_address'] ?? null;
        $customer = $order->getCustomer();
        if ($customer === null) {
            $customer = $this->customerProvider->getOrCreateCustomer($email, $order->getBillingAddress());
            $order->setCustomer($customer);
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        $this->orderManager->flush();

        return new JsonResponse(['order_id' => $order->getId()]);
    }
}
