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

namespace Sylius\AdyenPlugin\Provider;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Entity\ShopperReferenceInterface;
use Sylius\AdyenPlugin\Filter\PaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Filter\StoredPaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Mapper\PaymentMethodsMapperInterface;
use Sylius\AdyenPlugin\Model\PaymentMethodData;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Resolver\ShopperReferenceResolverInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\ShopUserInterface;

final class PaymentMethodsProvider implements PaymentMethodsProviderInterface
{
    use GatewayConfigFromPaymentTrait;

    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly PaymentMethodsFilterInterface $paymentMethodsFilter,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private readonly StoredPaymentMethodsFilterInterface $storedPaymentMethodsFilter,
        private readonly PaymentMethodsMapperInterface $paymentMethodsMapper,
        private readonly ShopperReferenceResolverInterface $shopperReferenceResolver,
        private readonly CurrentShopUserProviderInterface $currentShopUserProvider,
    ) {
    }

    public function provideForOrder(PaymentMethodInterface $adyenPaymentMethod, OrderInterface $order): PaymentMethodData
    {
        $client = $this->adyenClientProvider->getClientForCode($adyenPaymentMethod->getCode());

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        $currentShopUser = $this->currentShopUserProvider->getShopUser();
        $shopperReference = $this->resolveShopperReference($adyenPaymentMethod, $customer, $currentShopUser);

        $isManualCapture = $this->adyenPaymentMethodChecker->isCaptureMode(
            $adyenPaymentMethod,
            PaymentCaptureMode::MANUAL,
        );

        $response = $client->getPaymentMethodsResponse(
            $order,
            $shopperReference,
            $isManualCapture,
        );

        $available = $this->paymentMethodsMapper->mapAvailable($response->getPaymentMethods() ?? []);
        $stored = $this->paymentMethodsMapper->mapStored($response->getStoredPaymentMethods() ?? []);

        $availableFiltered = $this->paymentMethodsFilter->filter($available, [
            'order' => $order,
            'payment_method' => $adyenPaymentMethod,
            'manual_capture' => $isManualCapture,
            'guest' => null === $currentShopUser,
        ]);

        return new PaymentMethodData(
            paymentMethods: $availableFiltered,
            storedPaymentMethods: $this->storedPaymentMethodsFilter->filterAgainstAvailable($stored, $availableFiltered),
        );
    }

    private function resolveShopperReference(
        PaymentMethodInterface $paymentMethod,
        ?CustomerInterface $orderCustomer,
        ?ShopUserInterface $shopUser,
    ): ?ShopperReferenceInterface {
        $orderUser = $orderCustomer?->getUser();
        if ($orderUser === null || $orderUser !== $shopUser) {
            return null;
        }

        return $this->shopperReferenceResolver->resolve($paymentMethod, $orderCustomer);
    }
}
