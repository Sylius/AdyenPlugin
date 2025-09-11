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

use Sylius\AdyenPlugin\Processor\Order\OrderPaymentProcessorInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\Event;

final class ProcessOrderPaymentAfterReversalListener
{
    public function __construct(
        private readonly OrderPaymentProcessorInterface $orderPaymentProcessor,
    ) {
    }

    public function __invoke(Event $event): void
    {
        $payment = $event->getSubject();
        if ($payment instanceof PaymentInterface && null !== $payment->getOrder()) {
            $this->orderPaymentProcessor->process($payment->getOrder());
        }
    }
}
