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
use Sylius\AdyenPlugin\Exception\AdyenPaymentMethodNotFoundException;
use Sylius\AdyenPlugin\Filter\PaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Filter\StoredPaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Mapper\PaymentMethodsMapperInterface;
use Sylius\AdyenPlugin\Model\PaymentMethodData;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\ShopperReferenceResolverInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class PaymentMethodsProvider implements PaymentMethodsProviderInterface
{
    use GatewayConfigFromPaymentTrait;

    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentMethodsFilterInterface $paymentMethodsFilter,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private readonly StoredPaymentMethodsFilterInterface $storedPaymentMethodsFilter,
        private readonly PaymentMethodsMapperInterface $paymentMethodsMapper,
        private readonly ShopperReferenceResolverInterface $shopperReferenceResolver,
        private readonly CurrentShopUserProviderInterface $currentShopUserProvider,
    ) {
    }

    public function provideForOrder(string $paymentMethodCode, OrderInterface $order): PaymentMethodData
    {
        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($paymentMethodCode);
        if (null === $paymentMethod) {
            throw new AdyenPaymentMethodNotFoundException($paymentMethodCode);
        }

        $client = $this->adyenClientProvider->getClientForCode($paymentMethodCode);

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        $shopperReference = $this->resolveShopperReference($paymentMethod, $customer);

        $response = $client->getPaymentMethodsResponse(
            $order,
            $shopperReference,
            $this->adyenPaymentMethodChecker->isCaptureMode($paymentMethod, PaymentCaptureMode::MANUAL),
        );

        $available = $this->paymentMethodsMapper->mapAvailable($response->getPaymentMethods() ?? []);
        $stored = $this->paymentMethodsMapper->mapStored($response->getStoredPaymentMethods() ?? []);

        $availableFiltered = $this->paymentMethodsFilter->filter($available, [
            'order' => $order,
            'payment_method' => $paymentMethod,
        ]);

        return new PaymentMethodData(
            paymentMethods: $availableFiltered,
            storedPaymentMethods: $this->storedPaymentMethodsFilter->filterAgainstAvailable($stored, $availableFiltered),
        );
    }

    private function resolveShopperReference(
        PaymentMethodInterface $paymentMethod,
        ?CustomerInterface $orderCustomer,
    ): ?ShopperReferenceInterface {
        $orderUser = $orderCustomer?->getUser();
        if ($orderUser === null || $orderUser !== $this->currentShopUserProvider->getShopUser()) {
            return null;
        }

        return $this->shopperReferenceResolver->resolve($paymentMethod, $orderCustomer);
    }
}
