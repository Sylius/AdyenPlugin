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
use Symfony\Component\Workflow\Event\GuardEvent;

final class GuardCompleteListener
{
    public function __construct(private readonly object $guardService)
    {
    }

    public function __invoke(GuardEvent $event): void
    {
        $payment = $event->getSubject();
        if (!$payment instanceof PaymentInterface) {
            return;
        }

        if (method_exists($this->guardService, 'canBeCompleted') && !$this->guardService->canBeCompleted($payment)) {
            $event->setBlocked(true);
        }
    }
}
