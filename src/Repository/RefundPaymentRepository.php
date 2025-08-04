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

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

final class RefundPaymentRepository implements RefundPaymentRepositoryInterface
{
    /** @var EntityRepository */
    private $baseRepository;

    public function __construct(EntityRepository $baseRepository)
    {
        $this->baseRepository = $baseRepository;
    }

    public function getForOrderNumberAndRefundPaymentId(
        string $orderNumber,
        int $paymentId,
    ): RefundPaymentInterface {
        $qb = $this->baseRepository->createQueryBuilder('rp');
        $qb
            ->select('rp')
            ->innerJoin('rp.order', 'o')
            ->where('rp.id=:id')
            ->andWhere('o.number=:order_number')
            ->setParameters([
                'id' => $paymentId,
                'order_number' => $orderNumber,
            ])
        ;

        return $qb->getQuery()->getSingleResult();
    }

    public function find(int $id): ?RefundPaymentInterface
    {
        /** @var RefundPaymentInterface|null $result */
        $result = $this->baseRepository->find($id);

        return $result;
    }
}
