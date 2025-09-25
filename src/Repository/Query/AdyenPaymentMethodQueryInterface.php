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

namespace Sylius\AdyenPlugin\Repository\Query;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface AdyenPaymentMethodQueryInterface
{
    public function getOneAdyenForCode(string $code): ?PaymentMethodInterface;

    public function findOneAdyenByChannel(ChannelInterface $channel): ?PaymentMethodInterface;

    /** @return PaymentMethodInterface[] */
    public function findAllAdyenByChannel(ChannelInterface $channel): array;
}
