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

namespace Sylius\AdyenPlugin\EventListener\Workflow\OrderPayment;

use Sylius\AdyenPlugin\StateMachine\Guard\OrderPaymentGuard;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\GuardEvent;

final class GuardCancelListener
{
    public function __construct(private readonly OrderPaymentGuard $guard)
    {
    }

    public function __invoke(GuardEvent $event): void
    {
        $order = $event->getSubject();
        if ($order instanceof OrderInterface && !$this->guard->canBeCancelled($order)) {
            $event->setBlocked(true);
        }
    }
}
