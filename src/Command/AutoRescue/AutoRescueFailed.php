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

namespace Sylius\AdyenPlugin\Command\AutoRescue;

final class AutoRescueFailed
{
    public function __construct(
        public readonly string $merchantReference,
        public readonly string $pspReference,
    ) {
    }
}
