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

namespace Tests\Sylius\AdyenPlugin\Functional\Stub;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessageBusSpy implements MessageBusInterface
{
    private array $dispatchedMessages = [];

    private MessageBusInterface $decoratedBus;

    public function __construct(MessageBusInterface $decoratedBus)
    {
        $this->decoratedBus = $decoratedBus;
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $this->dispatchedMessages[] = $message;

        return $this->decoratedBus->dispatch($message, $stamps);
    }

    public function getDispatchedMessages(): array
    {
        return $this->dispatchedMessages;
    }

    public function clearDispatchedMessages(): void
    {
        $this->dispatchedMessages = [];
    }
}
