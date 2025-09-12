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

use Sylius\AdyenPlugin\StateMachine\Guard\AdyenPaymentGuard;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\GuardEvent;

final class GuardCancelListener
{
    public function __construct(private AdyenPaymentGuard $guard)
    {
    }

    public function __invoke(GuardEvent $event): void
    {
        $p = $event->getSubject();
        if ($p instanceof PaymentInterface && !$this->guard->canBeCancelled($p)) {
            $event->setBlocked(true);
        }
    }
}
