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

namespace Tests\Sylius\AdyenPlugin\Unit\Provider;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Provider\CurrentShopUserProvider;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class CurrentShopUserProviderTest extends TestCase
{
    public function testItReturnsNullWhenNoToken(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $provider = new CurrentShopUserProvider($tokenStorage);

        self::assertNull($provider->getShopUser());
    }

    public function testItReturnsNullWhenUserIsNotShopUser(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->createMock(UserInterface::class));

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $provider = new CurrentShopUserProvider($tokenStorage);

        self::assertNull($provider->getShopUser());
    }

    public function testItReturnsShopUserWhenUserIsShopUser(): void
    {
        $shopUser = $this->createMock(ShopUserInterface::class);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($shopUser);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $provider = new CurrentShopUserProvider($tokenStorage);

        self::assertSame($shopUser, $provider->getShopUser());
    }
}
