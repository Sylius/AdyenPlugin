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
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    /** @var EntityRepository */
    private $baseRepository;

    public function __construct(EntityRepository $baseRepository)
    {
        $this->baseRepository = $baseRepository;
    }

    public function find(int $id): ?PaymentMethodInterface
    {
        /** @var PaymentMethodInterface|null $result */
        $result = $this->baseRepository->find($id);

        return $result;
    }

    public function getOneForAdyenAndCode(string $code): PaymentMethodInterface
    {
        return $this->baseRepository->createQueryBuilder('o')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->where('gatewayConfig.factoryName = :factoryName')
            ->andWhere('o.code = :code')
            ->setParameter('factoryName', AdyenClientProviderInterface::FACTORY_NAME)
            ->setParameter('code', $code)
            ->getQuery()
            ->getSingleResult()
        ;
    }

    public function findOneForAdyenAndCode(string $code): ?PaymentMethodInterface
    {
        try {
            return $this->getOneForAdyenAndCode($code);
        } catch (NoResultException $ex) {
            return null;
        }
    }

    private function getQueryForChannel(ChannelInterface $channel): QueryBuilder
    {
        return $this->baseRepository->createQueryBuilder('o')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->andWhere('o.enabled = true')
            ->andWhere(':channel MEMBER OF o.channels')
            ->andWhere('gatewayConfig.factoryName = :factoryName')
            ->setParameter('channel', $channel)
            ->setParameter('factoryName', AdyenClientProviderInterface::FACTORY_NAME)
            ->addOrderBy('o.position')
        ;
    }

    public function findOneByChannel(ChannelInterface $channel): ?PaymentMethodInterface
    {
        return $this
            ->getQueryForChannel($channel)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return array<int, PaymentMethodInterface>
     */
    public function findAllByChannel(ChannelInterface $channel): array
    {
        return $this
            ->getQueryForChannel($channel)
            ->getQuery()
            ->getResult()
        ;
    }
}
