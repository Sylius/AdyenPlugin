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

use Sylius\AdyenPlugin\Exception\AdyenNotFoundException;
use Sylius\AdyenPlugin\Filter\PaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Filter\StoredPaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Mapper\PaymentMethodsMapperInterface;
use Sylius\AdyenPlugin\Model\PaymentMethodData;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\ShopperReferenceResolverInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Messenger\HandleTrait;

final class PaymentMethodsProvider implements PaymentMethodsProviderInterface
{
    use GatewayConfigFromPaymentTrait;
    use HandleTrait;

    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentMethodsFilterInterface $paymentMethodsFilter,
        private readonly StoredPaymentMethodsFilterInterface $storedPaymentMethodsFilter,
        private readonly PaymentMethodsMapperInterface $paymentMethodsMapper,
        private readonly ShopperReferenceResolverInterface $shopperReferenceResolver,
    ) {
    }

    public function provideForOrder(string $paymentMethodCode, OrderInterface $order): PaymentMethodData
    {
        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($paymentMethodCode);

        if ($paymentMethod === null) {
            throw new AdyenNotFoundException(sprintf('Payment method "%s" not found.', $paymentMethodCode));
        }

        /** @var CustomerInterface $customer */
        $customer = $order->getCustomer();

        $shopperReference = $customer !== null && $customer->hasUser() === true
            ? $this->shopperReferenceResolver->resolve($paymentMethod, $customer)
            : null;

        $client = $this->adyenClientProvider->getClientForCode($paymentMethodCode);

        $response = $client->getPaymentMethodsResponse($order, $shopperReference);

        $available = $this->paymentMethodsMapper->mapAvailable($response->getPaymentMethods());
        $available = $this->paymentMethodsFilter->filter($available);
        $stored = $this->paymentMethodsMapper->mapStored($response->getStoredPaymentMethods());

        return new PaymentMethodData(
            paymentMethods: $available,
            storedPaymentMethods: $this->storedPaymentMethodsFilter->filterAgainstAvailable($stored, $available),
        );
    }
}
