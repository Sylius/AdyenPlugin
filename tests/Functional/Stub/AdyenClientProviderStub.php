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

use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class AdyenClientProviderStub implements AdyenClientProviderInterface
{
    public function __construct(
        private AdyenClientInterface $client,
    ) {
    }

    public function getDefaultClient(): AdyenClientInterface
    {
        return $this->client;
    }

    public function getClientForCode(string $code): AdyenClientInterface
    {
        return $this->client;
    }

    public function getForPaymentMethod(PaymentMethodInterface $paymentMethod): AdyenClientInterface
    {
        return $this->client;
    }
}
