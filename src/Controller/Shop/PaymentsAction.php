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

use Sylius\AdyenPlugin\Bus\Command\PaymentStatusReceived;
use Sylius\AdyenPlugin\Bus\Command\PrepareOrderForPayment;
use Sylius\AdyenPlugin\Bus\Command\TakeOverPayment;
use Sylius\AdyenPlugin\Bus\DispatcherInterface;
use Sylius\AdyenPlugin\Bus\Query\GetToken;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessorInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\AdyenPlugin\Traits\PayableOrderPaymentTrait;
use Sylius\AdyenPlugin\Traits\PaymentFromOrderTrait;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentsAction
{
    use PayableOrderPaymentTrait;
    use PaymentFromOrderTrait;

    public const REDIRECT_TARGET_ACTION = 'sylius_adyen_shop_thank_you';

    public const ORDER_ID_KEY = 'sylius_order_id';

    /** @var AdyenClientProviderInterface */
    private $adyenClientProvider;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var PaymentCheckoutOrderResolverInterface */
    private $paymentCheckoutOrderResolver;

    /** @var DispatcherInterface */
    private $dispatcher;

    /** @var PaymentResponseProcessorInterface */
    private $paymentResponseProcessor;

    public function __construct(
        AdyenClientProviderInterface $adyenClientProvider,
        UrlGeneratorInterface $urlGenerator,
        PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        DispatcherInterface $dispatcher,
        PaymentResponseProcessorInterface $paymentResponseProcessor,
    ) {
        $this->adyenClientProvider = $adyenClientProvider;
        $this->urlGenerator = $urlGenerator;
        $this->paymentCheckoutOrderResolver = $paymentCheckoutOrderResolver;
        $this->dispatcher = $dispatcher;
        $this->paymentResponseProcessor = $paymentResponseProcessor;
    }

    private function prepareTargetUrl(OrderInterface $order): string
    {
        $method = $this->getMethod(
            $this->getPayment($order),
        );

        return $this->urlGenerator->generate(
            self::REDIRECT_TARGET_ACTION,
            [
                'code' => $method->getCode(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function prepareOrder(Request $request, OrderInterface $order): void
    {
        if (null === $request->get('tokenValue')) {
            $request->getSession()->set(self::ORDER_ID_KEY, $order->getId());
        }

        $this->dispatcher->dispatch(new PrepareOrderForPayment($order));
    }

    public function __invoke(Request $request, ?string $code = null): JsonResponse
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();
        $this->prepareOrder($request, $order);

        if (null !== $code) {
            $this->dispatcher->dispatch(new TakeOverPayment($order, $code));
        }

        $payment = $this->getPayablePayment($order);
        $url = $this->prepareTargetUrl($order);
        $paymentMethod = $this->getMethod($payment);
        /**
         * @var AdyenTokenInterface $customerIdentifier
         */
        $customerIdentifier = $this->dispatcher->dispatch(new GetToken($paymentMethod, $order));

        $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);
        $result = $client->submitPayment(
            $url,
            $request->request->all(),
            $order,
            $customerIdentifier,
        );

        $payment->setDetails($result);
        $this->dispatcher->dispatch(new PaymentStatusReceived($payment));

        return new JsonResponse(
            $payment->getDetails()
            +
            [
                'redirect' => $this->paymentResponseProcessor->process(
                    (string) $paymentMethod->getCode(),
                    $request,
                    $payment,
                ),
            ],
        );
    }
}
