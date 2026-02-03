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

namespace Sylius\AdyenPlugin;

use Sylius\AdyenPlugin\DependencyInjection\Compiler\AddOrderPaymentWorkflowTransitionPass;
use Sylius\AdyenPlugin\DependencyInjection\Compiler\AddPaymentWorkflowTransitionPass;
use Sylius\AdyenPlugin\DependencyInjection\Compiler\RemoveCancelPaymentListenerPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Telemetry\TelemetryCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SyliusAdyenPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new AddOrderPaymentWorkflowTransitionPass());
        $container->addCompilerPass(new AddPaymentWorkflowTransitionPass());
        $container->addCompilerPass(new RemoveCancelPaymentListenerPass());
        $container->addCompilerPass(new TelemetryCompilerPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
