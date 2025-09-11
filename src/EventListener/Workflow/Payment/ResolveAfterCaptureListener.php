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
use Sylius\Component\Order\StateResolver\StateResolverInterface;
use Symfony\Component\Workflow\Event\Event;

final class ResolveAfterCaptureListener
{
    public function __construct(private readonly StateResolverInterface $resolver)
    {
    }

    public function __invoke(Event $event): void
    {
        $p = $event->getSubject();
        if ($p instanceof PaymentInterface && null !== $p->getOrder()) {
            $this->resolver->resolve($p->getOrder());
        }
    }
}
