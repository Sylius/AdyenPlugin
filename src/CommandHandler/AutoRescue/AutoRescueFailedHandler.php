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

namespace Sylius\AdyenPlugin\CommandHandler\AutoRescue;

use Sylius\AdyenPlugin\Command\AutoRescue\AutoRescueFailed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AutoRescueFailedHandler
{
    public function __invoke(AutoRescueFailed $command): void
    {
    }
}
