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

namespace Sylius\AdyenPlugin\EventListener\Workflow\Refund;

use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\StateResolver\OrderFullyRefundedStateResolverInterface;
use Symfony\Component\Workflow\Event\Event;

final class ResolveOrderFullyRefundedListener
{
    public function __construct(private readonly OrderFullyRefundedStateResolverInterface $resolver)
    {
    }

    public function __invoke(Event $event): void
    {
        $refundPayment = $event->getSubject();
        if ($refundPayment instanceof RefundPaymentInterface) {
            $this->resolver->resolve($refundPayment->getOrder()->getNumber());
        }
    }
}
