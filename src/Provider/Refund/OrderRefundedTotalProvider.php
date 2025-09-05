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

namespace Sylius\AdyenPlugin\Provider\Refund;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Repository\RefundPaymentRepositoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Provider\OrderRefundedTotalProviderInterface;

final class OrderRefundedTotalProvider implements OrderRefundedTotalProviderInterface
{
    public function __construct(
        private readonly OrderRefundedTotalProviderInterface $decorated,
        private readonly RefundPaymentRepositoryInterface $refundPaymentRepository,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function __invoke(OrderInterface $order): int
    {
        $payment = $order->getLastPayment();
        if (
            null === $payment ||
            !$this->adyenPaymentMethodChecker->isAdyenPayment($payment)
        ) {
            return $this->decorated->__invoke($order);
        }

        $refundPayments = $this->refundPaymentRepository->findBy(['order' => $order]);

        $orderRefundedTotal = 0;
        /** @var RefundPaymentInterface $refundPayment */
        foreach ($refundPayments as $refundPayment) {
            if ($refundPayment->getState() === RefundPaymentInterface::STATE_COMPLETED) {
                $orderRefundedTotal += $refundPayment->getAmount();
            }
        }

        return $orderRefundedTotal;
    }
}
