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

namespace Sylius\AdyenPlugin\Exception;

class TokenRemovalFailureException extends \InvalidArgumentException
{
    public static function forAnonymous(): self
    {
        return new self('Cannot delete token for anonymous user');
    }

    public static function forNonExistingToken(): self
    {
        return new self('Cannot delete non-existing token');
    }
}
