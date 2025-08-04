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

use Sylius\AdyenPlugin\Processor\PaymentResponseProcessorInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\AdyenPlugin\Traits\PayableOrderPaymentTrait;
use Sylius\AdyenPlugin\Traits\PaymentFromOrderTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentDetailsAction
{
    use PayableOrderPaymentTrait;
    use PaymentFromOrderTrait;

    /** @var AdyenClientProviderInterface */
    private $adyenClientProvider;

    /** @var PaymentCheckoutOrderResolverInterface */
    private $paymentCheckoutOrderResolver;

    /** @var PaymentResponseProcessorInterface */
    private $paymentResponseProcessor;

    public function __construct(
        AdyenClientProviderInterface $adyenClientProvider,
        PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        PaymentResponseProcessorInterface $paymentResponseProcessor,
    ) {
        $this->adyenClientProvider = $adyenClientProvider;
        $this->paymentCheckoutOrderResolver = $paymentCheckoutOrderResolver;
        $this->paymentResponseProcessor = $paymentResponseProcessor;
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();
        $payment = $this->getPayablePayment($order);
        $paymentMethod = $this->getMethod($payment);

        $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);
        $result = $client->paymentDetails($request->request->all());

        $payment->setDetails($result);

        return new JsonResponse(
            $payment->getDetails()
            + [
                'redirect' => $this->paymentResponseProcessor->process(
                    (string) $paymentMethod->getCode(),
                    $request,
                    $payment,
                ),
            ],
        );
    }
}
