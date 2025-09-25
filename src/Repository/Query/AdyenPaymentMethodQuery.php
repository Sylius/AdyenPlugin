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

use Doctrine\ORM\QueryBuilder;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;

final class AdyenPaymentMethodQuery implements AdyenPaymentMethodQueryInterface
{
    /** @param PaymentMethodRepositoryInterface&EntityRepository $repository */
    public function __construct(
        private PaymentMethodRepositoryInterface $repository,
    ) {
    }

    public function getOneAdyenForCode(string $code): ?PaymentMethodInterface
    {
        return $this->repository->createQueryBuilder('o')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->where('o.code = :code')
            ->andWhere('gatewayConfig.factoryName = :factoryName')
            ->setParameter('factoryName', AdyenClientProviderInterface::FACTORY_NAME)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneAdyenByChannel(ChannelInterface $channel): ?PaymentMethodInterface
    {
        return $this
            ->getQueryForChannel($channel)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findAllAdyenByChannel(ChannelInterface $channel): array
    {
        return $this
            ->getQueryForChannel($channel)
            ->getQuery()
            ->getResult()
        ;
    }

    private function getQueryForChannel(ChannelInterface $channel): QueryBuilder
    {
        return $this->repository->createQueryBuilder('o')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->andWhere('o.enabled = :enabled')
            ->andWhere(':channel MEMBER OF o.channels')
            ->andWhere('gatewayConfig.factoryName = :factoryName')
            ->setParameter('enabled', true)
            ->setParameter('channel', $channel)
            ->setParameter('factoryName', AdyenClientProviderInterface::FACTORY_NAME)
            ->addOrderBy('o.position')
        ;
    }
}
