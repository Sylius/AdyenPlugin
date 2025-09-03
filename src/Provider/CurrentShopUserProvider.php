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

namespace Sylius\AdyenPlugin\Provider;

use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class CurrentShopUserProvider implements CurrentShopUserProviderInterface
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public function getShopUser(): ?ShopUserInterface
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        return $user instanceof ShopUserInterface ? $user : null;
    }
}
