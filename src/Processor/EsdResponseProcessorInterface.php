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

namespace Sylius\AdyenPlugin\Processor;

interface EsdResponseProcessorInterface
{
    public function processEsdResponse(array $response): void;

    public function getEsdEligibilityLevel(array $response): ?string;

    public function hasEsdValidationErrors(array $response): bool;

    public function getEsdValidationErrors(array $response): array;
}
