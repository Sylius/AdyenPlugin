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
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\AddressProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\CustomerProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

class CheckoutAction
{
    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly ObjectManager $orderManager,
        private readonly AddressProviderInterface $addressProvider,
        private readonly CustomerProviderInterface $customerProvider,
        private readonly StateMachineInterface $stateMachine,
        private readonly ShippingMethodRepositoryInterface $shippingMethodRepository,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var OrderInterface $order */
        $order = $this->cartContext->getCart();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $email = $data['email'] ?? null;
        Assert::notNull($email);
        $shippingAddressData = $data['shippingAddress'] ?? null;
        Assert::isArray($shippingAddressData);
        $shippingOptionId = $data['shippingOptionId'] ?? null;
        Assert::notNull($shippingOptionId);

        $address = $this->addressProvider->createFullAddress($shippingAddressData);

        $order->setBillingAddress($address);
        $order->setShippingAddress($address);

        $customer = $order->getCustomer();
        if ($customer === null) {
            $customer = $this->customerProvider->getOrCreateCustomer($email, $address);
            $order->setCustomer($customer);
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_ADDRESS);

        $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $shippingOptionId]);
        $order->getShipments()->first()->setMethod($shippingMethod);
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING);

        $paymentMethod = $this->paymentMethodRepository->findOneBy(['code' => AdyenClientProviderInterface::FACTORY_NAME]);
        $order->getLastPayment(PaymentInterface::STATE_CART)->setMethod($paymentMethod);
        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        $this->orderManager->flush();

        return new JsonResponse(['order_id' => $order->getId()]);
    }
}
