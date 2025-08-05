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

use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

final class AdyenReferenceRepository extends EntityRepository implements AdyenReferenceRepositoryInterface
{
    private function getQueryBuilderForCodeAndReference(string $code, string $pspReference): QueryBuilder
    {
        $qb = $this
            ->createQueryBuilder('r')
            ->innerJoin('r.payment', 'p')
            ->innerJoin('p.method', 'pm')
            ->where('r.pspReference = :reference AND pm.code = :code')
            ->setParameters([
                'reference' => $pspReference,
                'code' => $code,
            ])
        ;

        return $qb;
    }

    public function getOneByCodeAndReference(string $code, string $pspReference): AdyenReferenceInterface
    {
        return $this->getQueryBuilderForCodeAndReference($code, $pspReference)->getQuery()->getSingleResult();
    }

    /**
     * @throws NoResultException
     */
    public function getOneForRefundByCodeAndReference(string $code, string $pspReference): AdyenReferenceInterface
    {
        $qb = $this->getQueryBuilderForCodeAndReference($code, $pspReference);
        $qb->andWhere('r.refundPayment IS NOT NULL');

        return $qb->getQuery()->getSingleResult();
    }
}
