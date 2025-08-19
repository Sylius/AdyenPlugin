<?php

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Provider\Refund;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodChecker;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\RefundPlugin\Provider\RefundPaymentMethodsProviderInterface;

final class SupportedRefundPaymentMethodsProvider implements RefundPaymentMethodsProviderInterface
{
    public function __construct(
        private readonly RefundPaymentMethodsProviderInterface $decoratedProvider,
    ) {
    }

    public function findForChannel(ChannelInterface $channel): array
    {
        return $this->decoratedProvider->findForChannel($channel);
    }

    public function findForOrder(OrderInterface $order): array
    {
        $methods = $this->decoratedProvider->findForOrder($order);
        $payment = $order->getLastPayment(PaymentInterface::STATE_COMPLETED);
        if (null === $payment || AdyenPaymentMethodChecker::isAdyenPayment($payment)) {
            return $methods;
        }

        return array_filter($methods, function (PaymentMethodInterface $method) {
            return false === AdyenPaymentMethodChecker::isAdyenPaymentMethod($method);
        });
    }
}
