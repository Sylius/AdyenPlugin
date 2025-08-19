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

trait RefundPaymentRepositoryTrait
{
    public function getForOrderNumberAndRefundPaymentId(
        string $orderNumber,
        int $paymentId,
    ): RefundPaymentInterface {
        return $this->createQueryBuilder('rp')
            ->innerJoin('rp.order', 'o')
            ->where('rp.id = :id')
            ->andWhere('o.number = :orderNumber')
            ->setParameter('id', $paymentId)
            ->setParameter('orderNumber', $orderNumber)
            ->getQuery()
            ->getSingleResult()
        ;
    }
}
