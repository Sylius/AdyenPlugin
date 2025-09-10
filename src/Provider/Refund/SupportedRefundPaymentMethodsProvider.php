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
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\RefundPlugin\Provider\RefundPaymentMethodsProviderInterface;

final class SupportedRefundPaymentMethodsProvider implements RefundPaymentMethodsProviderInterface
{
    public function __construct(
        private readonly RefundPaymentMethodsProviderInterface $decoratedProvider,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function findForOrder(OrderInterface $order): array
    {
        $methods = $this->decoratedProvider->findForOrder($order);
        $payment = $order->getLastPayment(PaymentInterface::STATE_COMPLETED);
        if (null === $payment || $this->adyenPaymentMethodChecker->isAdyenPayment($payment)) {
            return $methods;
        }

        return array_filter($methods, fn (PaymentMethodInterface $method) => false === $this->adyenPaymentMethodChecker->isAdyenPaymentMethod($method));
    }
}
