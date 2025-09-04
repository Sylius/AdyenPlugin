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

namespace Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\PayPal;

use Adyen\AdyenException;
use Sylius\AdyenPlugin\Bus\Command\PaymentStatusReceived;
use Sylius\AdyenPlugin\Bus\Command\PrepareOrderForPayment;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

final class InitializeAction
{
    public function __construct(
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly MessageBusInterface $messageBus,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $order = $this->paymentCheckoutOrderResolver->resolve();

        $data = json_decode($request->getContent(), true);
        Assert::isArray($data);

        $paymentMethod = $this->paymentMethodRepository->findOneAdyenByChannel($order->getChannel());
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        $this->messageBus->dispatch(new PrepareOrderForPayment($order));

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        $payment->setMethod($paymentMethod);

        try {
            $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);
            $result = $client->submitPaypalPayments(
                $data,
                $order,
                $this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::MANUAL),
            );
            $payment->setDetails($result);
            $this->messageBus->dispatch(new PaymentStatusReceived($payment));

            $this->paymentRepository->add($payment);

            return new JsonResponse($result);
        } catch (AdyenException $exception) {
            return new JsonResponse([
                'error' => true,
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ], $exception->getCode());
        }
    }
}
