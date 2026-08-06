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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\ConfiguredAdyenPaymentMethodChecker;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\PaymentMethodRepository;

final class ConfiguredAdyenPaymentMethodCheckerTest extends TestCase
{
    private MockObject|PaymentMethodRepository $paymentMethodRepository;

    private MockObject|QueryBuilder $queryBuilder;

    private MockObject|Query $query;

    private ConfiguredAdyenPaymentMethodChecker $checker;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepository::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->checker = new ConfiguredAdyenPaymentMethodChecker($this->paymentMethodRepository);
    }

    public function testReturnsTrueWhenAtLeastOneAdyenPaymentMethodExists(): void
    {
        $this->configureQueryBuilder();
        $this->query->method('getSingleScalarResult')->willReturn('3');

        self::assertTrue($this->checker->hasAdyenPaymentMethod());
    }

    public function testReturnsFalseWhenNoAdyenPaymentMethodExists(): void
    {
        $this->configureQueryBuilder();
        $this->query->method('getSingleScalarResult')->willReturn('0');

        self::assertFalse($this->checker->hasAdyenPaymentMethod());
    }

    private function configureQueryBuilder(): void
    {
        $this->paymentMethodRepository->method('createQueryBuilder')->with('o')->willReturn($this->queryBuilder);

        $this->queryBuilder->method('select')->with('COUNT(o.id)')->willReturnSelf();
        $this->queryBuilder->method('innerJoin')->with('o.gatewayConfig', 'gatewayConfig')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->with('gatewayConfig.factoryName = :factoryName')->willReturnSelf();
        $this->queryBuilder->method('setParameter')
            ->with('factoryName', AdyenClientProviderInterface::FACTORY_NAME)
            ->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
    }
}
