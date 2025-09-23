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

use Sylius\AdyenPlugin\Exception\AdyenPaymentMethodNotFoundException;
use Sylius\AdyenPlugin\Exception\OrderWithoutBillingAddressException;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DropinConfigurationProvider implements DropinConfigurationProviderInterface
{
    public const TRANSLATIONS = [
        'sylius_adyen.runtime.payment_failed_try_again',
    ];

    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentMethodsProviderInterface $paymentMethodsProvider,
        private readonly CurrentShopUserProviderInterface $currentShopUserProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getConfiguration(OrderInterface $order, string $paymentMethodCode): array
    {
        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($paymentMethodCode);
        if (null === $paymentMethod) {
            throw new AdyenPaymentMethodNotFoundException($paymentMethodCode);
        }

        $billingAddress = $order->getBillingAddress();
        if (null === $billingAddress) {
            throw new OrderWithoutBillingAddressException($order);
        }

        $config = $paymentMethod->getGatewayConfig()->getConfig();

        /** @var CustomerInterface|null $customer */
        $customer = $order->getCustomer();
        $currentShopUser = $this->currentShopUserProvider->getShopUser();
        $canStoreDetails = null !== $currentShopUser && $currentShopUser === $customer?->getUser();

        $pathParams = [
            'code' => $paymentMethodCode,
            'tokenValue' => $order->getTokenValue(),
        ];

        return [
            'billingAddress' => [
                'firstName' => $billingAddress->getFirstName(),
                'lastName' => $billingAddress->getLastName(),
                'countryCode' => $billingAddress->getCountryCode(),
                'province' => $billingAddress->getProvinceName() ?? $billingAddress->getProvinceCode(),
                'city' => $billingAddress->getCity(),
                'postcode' => $billingAddress->getPostcode(),
            ],
            'paymentMethods' => $this->paymentMethodsProvider->provideForOrder($paymentMethod, $order),
            'clientKey' => $config['clientKey'],
            'locale' => $order->getLocaleCode(),
            'environment' => $config['environment'],
            'enableStoreDetails' => $canStoreDetails,
            'amount' => [
                'currency' => $order->getCurrencyCode(),
                'value' => $order->getTotal(),
            ],
            'path' => [
                'payments' => $this->urlGenerator->generate('sylius_adyen_shop_payments', $pathParams),
                'paymentDetails' => $this->urlGenerator->generate('sylius_adyen_shop_payment_details', $pathParams),
                'deleteToken' => $this->urlGenerator->generate(
                    'sylius_adyen_shop_remove_token',
                    $pathParams + ['paymentReference' => '_REFERENCE_'],
                ),
            ],
            'translations' => $this->getTranslations(),
        ];
    }

    private function getTranslations(): array
    {
        $result = [];
        foreach (self::TRANSLATIONS as $key) {
            $result[$key] = $this->translator->trans($key);
        }

        return $result;
    }
}
