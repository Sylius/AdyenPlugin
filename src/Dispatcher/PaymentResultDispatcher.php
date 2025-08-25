<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Dispatcher;

use Sylius\AdyenPlugin\Dto\PaymentResult;
use Sylius\AdyenPlugin\Enum\PaymentResultType;
use Sylius\AdyenPlugin\Event\PaymentAuthorisedEvent;
use Sylius\AdyenPlugin\Event\PaymentFailedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class PaymentResultDispatcher implements PaymentResultDispatcherInterface
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function dispatch(PaymentResult $paymentResult): void
    {
        match ($paymentResult->result) {
            PaymentResultType::Authorised => $this->eventDispatcher->dispatch(new PaymentAuthorisedEvent($paymentResult->paymentId)),
            PaymentResultType::Failed => $this->eventDispatcher->dispatch(new PaymentFailedEvent($paymentResult->paymentId)),
            default => throw new \InvalidArgumentException('Unknown payment result type: ' . $paymentResult->result->value)
        };
    }
}
