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

namespace Sylius\AdyenPlugin\Controller\Shop;

use Sylius\AdyenPlugin\Callback\PreserveOrderTokenUponRedirectionCallback;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProviderInterface;
use Sylius\AdyenPlugin\Repository\AdyenTokenRepositoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class DropinConfigurationAction
{
    public const TRANSLATIONS = [
        'sylius_adyen.runtime.payment_failed_try_again',
    ];

    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly PaymentMethodsProviderInterface $paymentMethodsProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TranslatorInterface $translator,
        private readonly AdyenTokenRepositoryInterface $adyenTokenRepository,
    ) {
    }

    public function __invoke(
        Request $request,
        string $code,
        ?string $orderToken = null,
    ): JsonResponse {
        $order = $this->getOrder($orderToken);

        if (null === $order || null === $order->getId()) {
            return $this->getResponseForDroppedOrder($request);
        }

        $paymentMethodsData = $this->paymentMethodsProvider->provideForOrder($code, $order);

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $order->getLastPayment()->getMethod();
        $config = $paymentMethod->getGatewayConfig()->getConfig();

        $billingAddress = $order->getBillingAddress();
        Assert::isInstanceOf($billingAddress, AddressInterface::class);

        $pathParams = [
            'code' => $code,
            'tokenValue' => $order->getTokenValue(),
        ];
        /** @var CustomerInterface $customer */
        $customer = $order->getCustomer();

        return new JsonResponse([
            'billingAddress' => [
                'firstName' => $billingAddress->getFirstName(),
                'lastName' => $billingAddress->getLastName(),
                'countryCode' => $billingAddress->getCountryCode(),
                'province' => $billingAddress->getProvinceName() ?? $billingAddress->getProvinceCode(),
                'city' => $billingAddress->getCity(),
                'postcode' => $billingAddress->getPostcode(),
            ],
            'paymentMethods' => $paymentMethodsData,
            'clientKey' => $config['clientKey'],
            'locale' => $order->getLocaleCode(),
            'environment' => $config['environment'],
            'canBeStored' => null !== $this->adyenTokenRepository->findOneByPaymentMethodAndCustomer($paymentMethod, $customer),
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
        ]);
    }

    private function getTranslations(): array
    {
        $result = [];
        foreach (self::TRANSLATIONS as $key) {
            $result[$key] = $this->translator->trans($key);
        }

        return $result;
    }

    private function getOrder(?string $orderToken = null): ?OrderInterface
    {
        if (null === $orderToken) {
            $order = $this->cartContext->getCart();
        } else {
            $order = $this->orderRepository->findOneByTokenValue($orderToken);

            if (null === $order) {
                $order = $this->orderRepository->findCartByTokenValue($orderToken);
            }
        }

        /**
         * @var ?OrderInterface $result
         */
        $result = $order;

        return $result;
    }

    private function getResponseForDroppedOrder(Request $request): JsonResponse
    {
        /**
         * @var ?string $tokenValue
         */
        $tokenValue = $request->getSession()->get(
            PreserveOrderTokenUponRedirectionCallback::NON_FINALIZED_CART_SESSION_KEY,
        );

        try {
            if (null === $tokenValue) {
                throw new NotFoundHttpException();
            }
        } finally {
            $request->getSession()->remove(
                PreserveOrderTokenUponRedirectionCallback::NON_FINALIZED_CART_SESSION_KEY,
            );
        }

        return new JsonResponse([
            'redirect' => $this->urlGenerator->generate('sylius_shop_order_show', [
                'tokenValue' => $tokenValue,
            ]),
        ]);
    }
}
