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

namespace Sylius\AdyenPlugin\Bus;

use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

final class Dispatcher implements DispatcherInterface
{
    use HandleTrait;

    /** @var PaymentCommandFactoryInterface */
    private $commandFactory;

    public function __construct(
        MessageBusInterface $messageBus,
        PaymentCommandFactoryInterface $commandFactory,
    ) {
        $this->messageBus = $messageBus;
        $this->commandFactory = $commandFactory;
    }

    public function getCommandFactory(): PaymentCommandFactoryInterface
    {
        return $this->commandFactory;
    }

    /**
     * @return mixed
     */
    public function dispatch(object $action)
    {
        return $this->handle($action);
    }
}
