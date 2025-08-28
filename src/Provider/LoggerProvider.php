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

namespace Sylius\AdyenPlugin\Provider;

use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerProvider implements LoggerProviderInterface
{
    public function __construct(private readonly HandlerInterface $handler)
    {
    }

    public function getLogger(): LoggerInterface
    {
        return new Logger('Adyen', [$this->handler]);
    }
}
