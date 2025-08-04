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

use Sylius\AdyenPlugin\Exception\PaymentMethodForReferenceNotFoundException;
use Sylius\AdyenPlugin\Exception\UnprocessablePaymentException;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Bundle\OrderBundle\Doctrine\ORM\OrderRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;

final class PaymentDetailsResolver implements PaymentDetailsResolverInterface
{
    /** @var OrderRepository */
    private $orderRepository;

    /** @var AdyenClientProviderInterface */
    private $adyenClientProvider;

    /** @var PaymentRepositoryInterface */
    private $paymentRepository;

    public function __construct(
        OrderRepository $orderRepository,
        AdyenClientProviderInterface $adyenClientProvider,
        PaymentRepositoryInterface $paymentRepository,
    ) {
        $this->orderRepository = $orderRepository;
        $this->adyenClientProvider = $adyenClientProvider;
        $this->paymentRepository = $paymentRepository;
    }

    private function createPayloadForDetails(string $referenceId): array
    {
        return [
            'details' => [
                'redirectResult' => $referenceId,
            ],
        ];
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

    public function resolve(string $code, string $referenceId): PaymentInterface
    {
        $client = $this->adyenClientProvider->getClientForCode($code);
        $result = $client->paymentDetails($this->createPayloadForDetails($referenceId));
        $payment = $this->getPaymentForReference((string) $result['merchantReference']);
        $payment->setDetails($result);

        $this->paymentRepository->add($payment);

        return $payment;
    }
}
