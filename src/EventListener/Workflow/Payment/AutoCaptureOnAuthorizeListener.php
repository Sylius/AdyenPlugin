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

use Sylius\AdyenPlugin\Processor\Payment\AuthorizationStateProcessorInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\Event;

final class AutoCaptureOnAuthorizeListener
{
    public function __construct(private readonly AuthorizationStateProcessorInterface $processor)
    {
    }

    public function __invoke(Event $event): void
    {
        $payment = $event->getSubject();
        if ($payment instanceof PaymentInterface) {
            $this->processor->process($payment);
        }
    }
}
