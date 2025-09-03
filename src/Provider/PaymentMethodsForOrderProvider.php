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
use Sylius\AdyenPlugin\Exception\AdyenNotFoundException;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\ShopperReferenceResolverInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Webmozart\Assert\Assert;

final class PaymentMethodsForOrderProvider implements PaymentMethodsForOrderProviderInterface
{
    use GatewayConfigFromPaymentTrait;

    public const CONFIGURATION_KEYS_WHITELIST = [
        'environment', 'merchantAccount', 'clientKey',
    ];

    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly ShopperReferenceResolverInterface $shopperReferenceResolver,
    ) {
    }

    public function provideConfiguration(OrderInterface $order, ?string $code = null): ?array
    {
        $paymentMethod = $this->getPaymentMethod($order, $code);

        /** @var CustomerInterface $customer */
        $customer = $order->getCustomer();

        $shopperReference = $customer !== null && $customer->hasUser() === true
            ? $this->shopperReferenceResolver->resolve($paymentMethod, $customer)
            : null;

        if (!$this->adyenPaymentMethodChecker->isAdyenPaymentMethod($paymentMethod)) {
            return null;
        }

        $result = $this->filterKeys(
            $this->getGatewayConfig($paymentMethod)->getConfig(),
        );
        $result['paymentMethods'] = $this->adyenPaymentMethods($order, $code, $shopperReference);
        $result['code'] = $paymentMethod->getCode();

        $result['enableStoreDetails'] = $shopperReference !== null;

        return $result;
    }

    private function adyenPaymentMethods(
        OrderInterface $order,
        ?string $code = null,
        ?ShopperReferenceInterface $shopperReference = null,
    ): array {
        $method = $this->getPaymentMethod($order, $code);
        if (null === $method) {
            throw new AdyenNotFoundException();
        }

        try {
            $client = $this->adyenClientProvider->getClientForCode((string) $method->getCode());
        } catch (\InvalidArgumentException) {
            return [];
        }

        return $client->getAvailablePaymentMethods(
            $order,
            $shopperReference,
        );
    }

    private function filterKeys(array $array): array
    {
        return array_intersect_key($array, array_flip(self::CONFIGURATION_KEYS_WHITELIST));
    }

    private function getPaymentMethod(OrderInterface $order, ?string $code = null): ?PaymentMethodInterface
    {
        if (null !== $code) {
            return $this->paymentMethodRepository->getOneAdyenForCode($code);
        }

        $method = $order->getLastPayment()?->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        return $method;
    }
}
