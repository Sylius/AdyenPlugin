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

namespace Sylius\AdyenPlugin\Resolver\Payment;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AdyenPlugin\Exception\PaymentMethodForReferenceNotFoundException;
use Sylius\AdyenPlugin\Exception\UnprocessablePaymentException;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;

final class PaymentDetailsResolver implements PaymentDetailsResolverInterface
{
    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(string $code, string $referenceId): PaymentInterface
    {
        $client = $this->adyenClientProvider->getClientForCode($code);
        $result = $client->paymentDetails($this->createPayloadForDetails($referenceId));
        $payment = $this->getPaymentForReference((string) $result['merchantReference']);
        $payment->setDetails($result);

        $this->entityManager->flush();

        return $payment;
    }

    private function getPaymentForReference(string $orderNumber): PaymentInterface
    {
        /**
         * @var ?OrderInterface $order
         */
        $order = $this->orderRepository->findOneByNumber($orderNumber);
        if (null === $order) {
            throw new PaymentMethodForReferenceNotFoundException($orderNumber);
        }

        $payment = $order->getLastPayment();
        if (null === $payment) {
            throw new UnprocessablePaymentException();
        }

        return $payment;
    }

    private function createPayloadForDetails(string $referenceId): array
    {
        return [
            'details' => [
                'redirectResult' => $referenceId,
            ],
        ];
    }
}
