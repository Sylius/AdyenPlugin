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
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Provider\PaymentMethodsForOrderProvider;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
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
    private const TRANSLATIONS = [
        'sylius_adyen.runtime.payment_failed_try_again',
    ];

    public function __construct(
        private readonly CartContextInterface $cartContext,
        private readonly PaymentMethodsForOrderProvider $paymentMethodsForOrderProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TranslatorInterface $translator,
    ) {}

    public function __invoke(
        Request $request,
        string $code,
        ?string $orderToken = null,
    ): JsonResponse {
        $order = $this->getOrder($orderToken);

        if (null === $order || null === $order->getId()) {
            return $this->getResponseForDroppedOrder($request);
        }

        $config = $this->paymentMethodsForOrderProvider->provideConfiguration($order, AdyenClientProviderInterface::FACTORY_NAME);
        Assert::isArray($config);

        $billingAddress = $order->getBillingAddress();
        Assert::isInstanceOf($billingAddress, AddressInterface::class);

        $pathParams = [
            'code' => $code,
            'tokenValue' => $order->getTokenValue(),
        ];

        return new JsonResponse([
            'billingAddress' => $this->normalizeBillingAddress($billingAddress),
            'paymentMethods' => $config['paymentMethods'],
            'clientKey' => $config['clientKey'],
            'locale' => $order->getLocaleCode(),
            'environment' => $config['environment'],
            'canBeStored' => $config['canBeStored'],
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

    private function normalizeBillingAddress(AddressInterface $billingAddress): array
    {
        return [
            'firstName' => $billingAddress->getFirstName(),
            'lastName' => $billingAddress->getLastName(),
            'countryCode' => $billingAddress->getCountryCode(),
            'province' => $billingAddress->getProvinceName() ?? $billingAddress->getProvinceCode(),
            'city' => $billingAddress->getCity(),
            'postcode' => $billingAddress->getPostcode(),
        ];
    }

    private function getTranslations(): array
    {
        $translated = array_map([$this->translator, 'trans'], self::TRANSLATIONS);

        return array_combine(self::TRANSLATIONS, $translated) ?: [];
    }

    private function getOrder(?string $orderToken = null): ?OrderInterface
    {
        if (null === $orderToken) {
            /** @var ?OrderInterface $cart */
            $cart = $this->cartContext->getCart();
            return $cart;
        }

        /** @var ?OrderInterface $order */
        $order = $this->orderRepository->findOneByTokenValue($orderToken)
            ?? $this->orderRepository->findCartByTokenValue($orderToken);

        return $order;
    }

    private function getResponseForDroppedOrder(Request $request): JsonResponse
    {
        $session = $request->getSession();

        /** @var ?string $tokenValue */
        $tokenValue = $session->get(
            PreserveOrderTokenUponRedirectionCallback::NON_FINALIZED_CART_SESSION_KEY,
        );

        // Always clear the session key regardless of outcome
        $session->remove(
            PreserveOrderTokenUponRedirectionCallback::NON_FINALIZED_CART_SESSION_KEY,
        );

        if (null === $tokenValue) {
            throw new NotFoundHttpException();
        }

        return new JsonResponse([
            'redirect' => $this->urlGenerator->generate('sylius_shop_order_show', [
                'tokenValue' => $tokenValue,
            ]),
        ]);
    }
}
