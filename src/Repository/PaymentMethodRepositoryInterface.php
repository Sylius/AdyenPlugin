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

namespace Sylius\AdyenPlugin\Repository;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface PaymentMethodRepositoryInterface
{
    public function find(int $id): ?PaymentMethodInterface;

    public function findOneByChannel(ChannelInterface $channel): ?PaymentMethodInterface;

    public function findOneForAdyenAndCode(string $code): ?PaymentMethodInterface;

    /**
     * @return PaymentMethodInterface[]
     */
    public function findAllByChannel(ChannelInterface $channel): array;

    public function getOneForAdyenAndCode(string $code): PaymentMethodInterface;
}
