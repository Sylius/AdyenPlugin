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

namespace Sylius\AdyenPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMainMenuListener
{
    public function addAdyenSection(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $adyenSection = $menu
            ->addChild('adyen')
            ->setLabel('sylius_adyen.ui.ayden_gateway_label')
            ->setLabelAttribute('icon', 'credit card outline')
            ->setExtra('always_open', true)
        ;

        $adyenSection
            ->addChild('logs', [
                'route' => 'sylius_adyen_admin_log_index',
            ])
            ->setLabel('sylius_adyen.ui.logs')
        ;
    }
}
