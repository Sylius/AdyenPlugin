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

namespace Sylius\AdyenPlugin\EventListener\Workflow\Order;

use Sylius\AdyenPlugin\StateMachine\Guard\OrderGuard;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Workflow\Event\Event;

final class GuardCancelOrderListener
{
    public function __construct(private readonly OrderGuard $guard)
    {
    }

    public function __invoke(Event $event): void
    {
        $order = $event->getSubject();
        if ($order instanceof OrderInterface && !$this->guard->canBeCancelled($order)) {
            $event->setBlocked(true);
        }
    }
}
