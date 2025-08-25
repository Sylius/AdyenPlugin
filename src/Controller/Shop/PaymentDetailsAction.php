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

use Sylius\AdyenPlugin\Classifier\PaymentResultClassifierInterface;
use Sylius\AdyenPlugin\Dispatcher\PaymentResultDispatcherInterface;
use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessorInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\AdyenPlugin\Traits\PayableOrderPaymentTrait;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentDetailsAction
{
    use PayableOrderPaymentTrait;

    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly RepositoryInterface $adyenPaymentDetailRepository,
        private readonly PaymentResultClassifierInterface $paymentResultClassifier,
        private readonly PaymentResultDispatcherInterface $paymentResultDispatcher,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();
        $payment = $this->getPayablePayment($order);

        /** @var AdyenPaymentDetailInterface $paymentDetail */
        $paymentDetail = $this->adyenPaymentDetailRepository->findOneBy(['payment' => $payment]);
        if (OrderCheckoutStates::STATE_COMPLETED !== $order->getCheckoutState() && $payment->getAmount() !== $paymentDetail->getAmount()) {
            return new JsonResponse([
                'error' => true,
                'code' => 'AMOUNT_MISMATCH',
                'message' => 'Your cart has been modified. Refresh the page and try again.',
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);
        $result = $client->paymentDetails($request->request->all());

        $payment->setDetails($result);
        $paymentResult = $this->paymentResultClassifier->classify($payment->getId(), $result);
        $this->paymentResultDispatcher->dispatch($paymentResult);

        return new JsonResponse($result);
    }
}
