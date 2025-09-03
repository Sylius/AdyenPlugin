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

use Adyen\AdyenException;
use Sylius\AdyenPlugin\Bus\Command\PaymentStatusReceived;
use Sylius\AdyenPlugin\Bus\Command\PrepareOrderForPayment;
use Sylius\AdyenPlugin\Bus\Command\TakeOverPayment;
use Sylius\AdyenPlugin\Clearer\PaymentReferencesClearerInterface;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessorInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Provider\CurrentShopUserProviderInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\AdyenPlugin\Resolver\ShopperReferenceResolverInterface;
use Sylius\AdyenPlugin\Traits\PayableOrderPaymentTrait;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaymentsAction
{
    use HandleTrait;
    use PayableOrderPaymentTrait;

    public const REDIRECT_TARGET_ACTION = 'sylius_adyen_shop_thank_you';

    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly PaymentResponseProcessorInterface $paymentResponseProcessor,
        private readonly PaymentReferencesClearerInterface $paymentReferencesClearer,
        private readonly ShopperReferenceResolverInterface $shopperReferenceResolver,
        private readonly CurrentShopUserProviderInterface $currentShopUserProvider,
        MessageBusInterface $messageBus,
    ) {
        $this->messageBus = $messageBus;
    }

    public function __invoke(Request $request, ?string $code = null): JsonResponse
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();
        $this->messageBus->dispatch(new PrepareOrderForPayment($order));

        if ($code !== null) {
            $this->messageBus->dispatch(new TakeOverPayment($order, $code));
        }

        $payment = $this->getPayablePayment($order);
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $url = $this->prepareTargetUrl($paymentMethod, $request);

        /** @var CustomerInterface|null $customer */
        $customer = $this->currentShopUserProvider->getShopUser()?->getCustomer();

        $shopperReference = $customer !== null
            ? $this->shopperReferenceResolver->resolve($paymentMethod, $customer)
            : null;

        $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);

        try {
            $this->paymentReferencesClearer->clear($payment);

            $result = $client->submitPayment(
                $url,
                $request->request->all(),
                $order,
                $shopperReference,
            );

            $payment->setDetails($result);
            $this->messageBus->dispatch(new PaymentStatusReceived($payment));

            return new JsonResponse($payment->getDetails() + [
                    'redirect' => $this->paymentResponseProcessor->process(
                        (string) $paymentMethod->getCode(),
                        $request,
                        $payment,
                    ),
                ]);
        } catch (AdyenException $exception) {
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ], $exception->getCode());
        }
    }

    private function prepareTargetUrl(PaymentMethodInterface $paymentMethod, Request $request): string
    {
        return $this->urlGenerator->generate(
            self::REDIRECT_TARGET_ACTION,
            [
                'code' => $paymentMethod->getCode(),
                '_locale' => $request->getLocale(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
