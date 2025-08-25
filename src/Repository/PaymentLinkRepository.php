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

use Doctrine\ORM\Query\Expr\Join;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\PaymentInterface;

class PaymentLinkRepository extends EntityRepository implements PaymentLinkRepositoryInterface
{
    public function findOneByPaymentAndLinkId(PaymentInterface $payment, string $paymentLinkId): ?PaymentLinkInterface
    {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.payment', 'payment', Join::WITH, 'o.payment = :payment')
            ->andWhere('o.paymentLinkId = :paymentLinkId')
            ->setParameter('payment', $payment)
            ->setParameter('paymentLinkId', $paymentLinkId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByPaymentMethodCodeAndLinkId(
        string $paymentMethodCode,
        string $paymentLinkId,
    ): ?PaymentLinkInterface {
        return $this->createQueryBuilder('o')
            ->innerJoin('o.payment', 'payment')
            ->innerJoin('payment.method', 'method')
            ->andWhere('method.code = :paymentMethodCode')
            ->andWhere('o.paymentLinkId = :paymentLinkId')
            ->setParameter('paymentMethodCode', $paymentMethodCode)
            ->setParameter('paymentLinkId', $paymentLinkId)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function removeByLinkId(string $paymentLinkId): void
    {
        $paymentLink = $this->findOneBy(['paymentLinkId' => $paymentLinkId]);
        if ($paymentLink !== null) {
            $this->remove($paymentLink);
        }
    }
}
