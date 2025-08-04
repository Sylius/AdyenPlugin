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

namespace Sylius\AdyenPlugin\Client;

use Adyen\Exception\HMACKeyValidationException;

interface SignatureValidatorInterface
{
    /**
     * @throws HMACKeyValidationException
     */
    public function isValid(array $params): bool;
}
