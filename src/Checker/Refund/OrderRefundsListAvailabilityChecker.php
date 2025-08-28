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

namespace Sylius\AdyenPlugin\Checker\Refund;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\RefundPlugin\Checker\OrderRefundingAvailabilityCheckerInterface;

final class OrderRefundsListAvailabilityChecker implements OrderRefundingAvailabilityCheckerInterface
{
    public function __construct(
        private readonly OrderRefundingAvailabilityCheckerInterface $decoratedChecker,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function __invoke(string $orderNumber): bool
    {
        /** @var OrderInterface|null $order */
        $order = $this->orderRepository->findOneByNumber($orderNumber);
        if ($order === null) {
            throw new \InvalidArgumentException(sprintf('Order with number "%s" does not exist.', $orderNumber));
        }

        $payment = $order->getLastPayment();
        if (null !== $payment && $this->paymentCannotBeRefunded($payment)) {
            return false;
        }

        return $this->decoratedChecker->__invoke($orderNumber);
    }

    private function paymentCannotBeRefunded(PaymentInterface $payment): bool
    {
        return
            $this->adyenPaymentMethodChecker->isAdyenPayment($payment) &&
            $this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::AUTOMATIC) &&
            in_array($payment->getState(), [
                PaymentGraph::STATE_PROCESSING_REVERSAL,
                PaymentInterface::STATE_COMPLETED,
                PaymentInterface::STATE_CANCELLED,
                PaymentInterface::STATE_REFUNDED,
            ], true)
        ;
    }
}
