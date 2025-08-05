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

use Adyen\Service\WebhookReceiver;
use Adyen\Util\HmacSignature;

final class SignatureValidator implements SignatureValidatorInterface
{
    public function __construct(private readonly string $key)
    {
    }

    public function isValid(array $params): bool
    {
        return $this->getReceiver()->validateHmac($params, $this->key);
    }

    private function getReceiver(): WebhookReceiver
    {
        return new WebhookReceiver(
            new HmacSignature(),
        );
    }
}
