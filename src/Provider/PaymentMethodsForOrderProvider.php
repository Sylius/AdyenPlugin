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
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Exception\AdyenNotFoundException;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

final class PaymentMethodsForOrderProvider implements PaymentMethodsForOrderProviderInterface
{
    use GatewayConfigFromPaymentTrait;
    use HandleTrait;

    public const CONFIGURATION_KEYS_WHITELIST = [
        'environment', 'merchantAccount', 'clientKey',
    ];

    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    public function provideConfiguration(OrderInterface $order, ?string $code = null): ?array
    {
        $paymentMethod = $this->getPaymentMethod($order, $code);
        $token = $this->getToken($paymentMethod, $order);

        if (!$this->adyenPaymentMethodChecker->isAdyenPaymentMethod($paymentMethod)) {
            return null;
        }

        $result = $this->filterKeys(
            $this->getGatewayConfig($paymentMethod)->getConfig(),
        );
        $result['paymentMethods'] = $this->adyenPaymentMethods($order, $code, $token);
        $result['code'] = $paymentMethod->getCode();
        $result['canBeStored'] = null !== $token;

        return $result;
    }

    private function getToken(PaymentMethodInterface $paymentMethod, OrderInterface $order): ?AdyenTokenInterface
    {
        /**
         * @var ?CustomerInterface $customer
         */
        $customer = $order->getCustomer();
        if (null === $customer || !$customer->hasUser()) {
            return null;
        }

        /**
         * @var AdyenTokenInterface $token
         */
        $token = $this->handle(new GetToken($paymentMethod, $order));

        return $token;
    }

    private function adyenPaymentMethods(
        OrderInterface $order,
        ?string $code = null,
        ?AdyenTokenInterface $adyenToken = null,
    ): array {
        $method = $this->getPaymentMethod($order, $code);
        if (null === $method) {
            throw new AdyenNotFoundException();
        }

        try {
            $client = $this->adyenClientProvider->getClientForCode((string) $method->getCode());
        } catch (\InvalidArgumentException $ex) {
            return [];
        }

        return $client->getAvailablePaymentMethods(
            $order,
            $adyenToken,
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
