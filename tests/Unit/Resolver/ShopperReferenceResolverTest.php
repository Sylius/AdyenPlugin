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

namespace Tests\Sylius\AdyenPlugin\Unit\Resolver;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Entity\ShopperReferenceInterface;
use Sylius\AdyenPlugin\Factory\ShopperReferenceFactoryInterface;
use Sylius\AdyenPlugin\Repository\ShopperReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\ShopperReferenceResolver;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class ShopperReferenceResolverTest extends TestCase
{
    public function testItReturnsExistingReferenceWithoutCreating(): void
    {
        $repository = $this->createMock(ShopperReferenceRepositoryInterface::class);
        $factory = $this->createMock(ShopperReferenceFactoryInterface::class);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $existingRef = $this->createMock(ShopperReferenceInterface::class);

        $repository
            ->expects(self::once())
            ->method('findOneByPaymentMethodAndCustomer')
            ->with($paymentMethod, $customer)
            ->willReturn($existingRef);

        $factory->expects(self::never())->method('create');
        $repository->expects(self::never())->method('add');

        $resolver = new ShopperReferenceResolver($repository, $factory);

        $result = $resolver->resolve($paymentMethod, $customer);

        self::assertSame($existingRef, $result);
    }

    public function testItCreatesAndPersistsReferenceWhenMissing(): void
    {
        $repository = $this->createMock(ShopperReferenceRepositoryInterface::class);
        $factory = $this->createMock(ShopperReferenceFactoryInterface::class);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $customer = $this->createMock(CustomerInterface::class);
        $createdRef = $this->createMock(ShopperReferenceInterface::class);

        $repository
            ->expects(self::once())
            ->method('findOneByPaymentMethodAndCustomer')
            ->with($paymentMethod, $customer)
            ->willReturn(null);

        $factory
            ->expects(self::once())
            ->method('create')
            ->with($paymentMethod, $customer)
            ->willReturn($createdRef);

        $repository
            ->expects(self::once())
            ->method('add')
            ->with($createdRef);

        $resolver = new ShopperReferenceResolver($repository, $factory);

        $result = $resolver->resolve($paymentMethod, $customer);

        self::assertSame($createdRef, $result);
    }
}
