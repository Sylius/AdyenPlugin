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

use Sylius\AdyenPlugin\Bus\Command\PrepareOrderForPayment;
use Sylius\AdyenPlugin\Bus\Command\TakeOverPayment;
use Sylius\AdyenPlugin\Bus\Query\GetToken;
use Sylius\AdyenPlugin\Classifier\PaymentResultClassifierInterface;
use Sylius\AdyenPlugin\Clearer\PaymentReferencesClearerInterface;
use Sylius\AdyenPlugin\Decider\PaymentRedirectDeciderInterface;
use Sylius\AdyenPlugin\Dispatcher\PaymentResultDispatcherInterface;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\AdyenPlugin\Traits\PayableOrderPaymentTrait;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentsAction
{
    use HandleTrait;
    use PayableOrderPaymentTrait;

    public const REDIRECT_TARGET_ACTION = 'sylius_adyen_shop_thank_you';

    public const ORDER_ID_KEY = 'sylius_order_id';

    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly PaymentReferencesClearerInterface $paymentReferencesClearer,
        private readonly PaymentRedirectDeciderInterface $paymentRedirectDecider,
        private readonly PaymentResultClassifierInterface $paymentResultClassifier,
        private readonly PaymentResultDispatcherInterface $paymentResultDispatcher,
        MessageBusInterface $messageBus
    ) {
        $this->messageBus = $messageBus;
    }

    public function __invoke(Request $request, ?string $code = null): JsonResponse
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();
        $this->prepareOrder($request, $order);

        if (null !== $code) {
            $this->messageBus->dispatch(new TakeOverPayment($order, $code));
        }

        $payment = $this->getPayablePayment($order);
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();
        $url = $this->prepareTargetUrl($paymentMethod, $request);
        /**
         * @var AdyenTokenInterface $customerIdentifier
         */
        $customerIdentifier = $this->handle(new GetToken($paymentMethod, $order));

        $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);

        $this->paymentReferencesClearer->clear($payment);

        $result = $client->submitPayment(
            $url,
            $request->request->all(),
            $order,
            $customerIdentifier,
        );

        // If Adyen returned an `action`, the frontend must continue the flow (3DS/redirect/etc.)
        if ($this->paymentRedirectDecider->shouldRedirect($result) === true) {
            return new JsonResponse($result);
        }

        $payment->setDetails($result);
        $paymentResult = $this->paymentResultClassifier->classify($payment->getId(), $result);
        $this->paymentResultDispatcher->dispatch($paymentResult);

        return new JsonResponse($result);
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

    private function prepareOrder(Request $request, OrderInterface $order): void
    {
        if (null === $request->get('tokenValue')) {
            $request->getSession()->set(self::ORDER_ID_KEY, $order->getId());
        }

        $this->messageBus->dispatch(new PrepareOrderForPayment($order));
    }
}
