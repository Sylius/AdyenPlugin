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

use Sylius\AdyenPlugin\Bus\Query\GetToken;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Exception\AdyenNotFoundException;
use Sylius\AdyenPlugin\Filter\PaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Filter\StoredPaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Mapper\PaymentMethodsMapperInterface;
use Sylius\AdyenPlugin\Model\PaymentMethodData;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

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
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    public function provideForOrder(string $paymentMethodCode, OrderInterface $order): PaymentMethodData
    {
        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($paymentMethodCode);
        if ($paymentMethod === null) {
            throw new AdyenNotFoundException(sprintf('Payment method "%s" not found.', $paymentMethodCode));
        }

        $token = $this->getToken($paymentMethod, $order);
        $client = $this->adyenClientProvider->getClientForCode($paymentMethodCode);

        $response = $client->getPaymentMethodsResponse($order, $token);

        $available = $this->paymentMethodsMapper->mapAvailable($response->getPaymentMethods());
        $available = $this->paymentMethodsFilter->filter($available);
        $stored = $this->paymentMethodsMapper->mapStored($response->getStoredPaymentMethods());

        return new PaymentMethodData(
            paymentMethods: $available,
            storedPaymentMethods: $this->storedPaymentMethodsFilter->filterAgainstAvailable($stored, $available),
        );
    }

    private function getToken(PaymentMethodInterface $paymentMethod, OrderInterface $order): ?AdyenTokenInterface
    {
        /** @var ?CustomerInterface $customer */
        $customer = $order->getCustomer();
        if ($customer === null || !$customer->hasUser()) {
            return null;
        }

        /** @var AdyenTokenInterface $token */
        $token = $this->handle(new GetToken($paymentMethod, $order));

        return $token;
    }
}
