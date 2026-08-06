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

namespace Sylius\AdyenPlugin\Checker;

use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;

final class ConfiguredAdyenPaymentMethodChecker implements ConfiguredAdyenPaymentMethodCheckerInterface
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface>&EntityRepository $paymentMethodRepository */
    public function __construct(
        private readonly EntityRepository&PaymentMethodRepositoryInterface $paymentMethodRepository,
    ) {
    }

    public function hasAdyenPaymentMethod(): bool
    {
        $count = (int) $this->paymentMethodRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->andWhere('gatewayConfig.factoryName = :factoryName')
            ->setParameter('factoryName', AdyenClientProviderInterface::FACTORY_NAME)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }
}
