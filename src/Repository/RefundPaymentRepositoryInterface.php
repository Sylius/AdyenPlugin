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

use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

interface RefundPaymentRepositoryInterface
{
    public function getForOrderNumberAndRefundPaymentId(string $orderNumber, int $paymentId): RefundPaymentInterface;

    public function find(int $id): ?RefundPaymentInterface;
}
