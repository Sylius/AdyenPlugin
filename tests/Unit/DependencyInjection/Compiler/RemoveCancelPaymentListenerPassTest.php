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

namespace Tests\Sylius\AdyenPlugin\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\DependencyInjection\Compiler\RemoveCancelPaymentListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class RemoveCancelPaymentListenerPassTest extends TestCase
{
    private const CANCEL_PAYMENT_LISTENER_SERVICE_ID = 'sylius.listener.workflow.order.cancel_payment';

    private RemoveCancelPaymentListenerPass $compilerPass;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->compilerPass = new RemoveCancelPaymentListenerPass();
        $this->container = new ContainerBuilder();
    }

    public function testItDoesNothingWhenOrderWorkflowDefinitionDoesNotExist(): void
    {
        $listenerDefinition = new Definition();
        $this->container->setDefinition(self::CANCEL_PAYMENT_LISTENER_SERVICE_ID, $listenerDefinition);

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition(self::CANCEL_PAYMENT_LISTENER_SERVICE_ID));
    }

    public function testItDoesNothingWhenCancelPaymentListenerDoesNotExist(): void
    {
        $orderDefinition = new Definition();
        $this->container->setDefinition('state_machine.sylius_order.definition', $orderDefinition);

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition(self::CANCEL_PAYMENT_LISTENER_SERVICE_ID));
    }

    public function testItRemovesListenerWhenBothDefinitionsExist(): void
    {
        $orderDefinition = new Definition();
        $this->container->setDefinition('state_machine.sylius_order.definition', $orderDefinition);

        $listenerDefinition = new Definition();
        $this->container->setDefinition(self::CANCEL_PAYMENT_LISTENER_SERVICE_ID, $listenerDefinition);

        $this->assertTrue($this->container->hasDefinition(self::CANCEL_PAYMENT_LISTENER_SERVICE_ID));

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition(self::CANCEL_PAYMENT_LISTENER_SERVICE_ID));
    }
}
