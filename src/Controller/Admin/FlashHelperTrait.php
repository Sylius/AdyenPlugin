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

namespace Sylius\AdyenPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

trait FlashHelperTrait
{
    protected readonly RequestStack $requestStack;

    protected function addFlash(string $type, string $message, array $parameters = []): void
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->requestStack->getSession()->getBag('flashes');
        $flashBag->add($type, [
            'message' => $message,
            'parameters' => $parameters,
        ]);
    }
}
