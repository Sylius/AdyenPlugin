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

namespace Sylius\AdyenPlugin\EventListener\Workflow\Payment;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Workflow\Event\Event;

final class ProcessFailedPaymentOrderListener
{
    public function __construct(private readonly OrderProcessorInterface $orderPaymentProcessor)
    {
    }

    public function __invoke(Event $event): void
    {
        $payment = $event->getSubject();
        $order = $payment->getOrder();
        if ($payment instanceof PaymentInterface && null !== $order) {
            $this->orderPaymentProcessor->process($order);
        }
    }
}
