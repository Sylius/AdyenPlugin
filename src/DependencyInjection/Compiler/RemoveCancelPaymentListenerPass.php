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

namespace Sylius\AdyenPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RemoveCancelPaymentListenerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (
            $container->hasDefinition('state_machine.sylius_order.definition') &&
            $container->hasDefinition('Sylius\Bundle\CoreBundle\EventListener\Workflow\Order\CancelPaymentListener')
        ) {
            $container->removeDefinition('Sylius\Bundle\CoreBundle\EventListener\Workflow\Order\CancelPaymentListener');
        }
    }
}
