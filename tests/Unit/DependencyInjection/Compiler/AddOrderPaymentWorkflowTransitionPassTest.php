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
use Sylius\AdyenPlugin\DependencyInjection\Compiler\AddOrderPaymentWorkflowTransitionPass;
use Sylius\Bundle\CoreBundle\SyliusCoreBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Transition;

final class AddOrderPaymentWorkflowTransitionPassTest extends TestCase
{
    private AddOrderPaymentWorkflowTransitionPass $compilerPass;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->compilerPass = new AddOrderPaymentWorkflowTransitionPass();
        $this->container = new ContainerBuilder();
    }

    public function testItDoesNothingWhenWorkflowDefinitionDoesNotExist(): void
    {
        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('state_machine.sylius_order_payment.definition'));
    }

    public function testItAddsRequiredTransitionsWhenWorkflowDefinitionExists(): void
    {
        $workflowDefinition = new Definition();
        $workflowDefinition->setArguments(['places', []]);
        $this->container->setDefinition('state_machine.sylius_order_payment.definition', $workflowDefinition);

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition('state_machine.sylius_order_payment.definition');
        $transitions = $definition->getArgument(1);

        $extra = self::isAuthorizedTransitionRequired() ? 1 : 0;
        $this->assertCount(2 + $extra, $transitions);

        $this->assertTransitionExists($transitions, 'request_payment', ['paid'], ['awaiting_payment']);
        $this->assertTransitionExists($transitions, 'cancel', ['paid'], ['cancelled']);

        if ($extra > 0) {
            $this->assertTransitionExists($transitions, 'request_payment', ['authorized'], ['awaiting_payment']);
        }
    }

    public function testItDoesNotAddDuplicateTransitions(): void
    {
        $existingTransition = new Definition(Transition::class);
        $existingTransition->setArguments(['request_payment', ['authorized'], ['awaiting_payment']]);

        $workflowDefinition = new Definition();
        $workflowDefinition->setArguments(['places', [$existingTransition]]);
        $this->container->setDefinition('state_machine.sylius_order_payment.definition', $workflowDefinition);

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition('state_machine.sylius_order_payment.definition');
        $transitions = $definition->getArgument(1);

        $this->assertCount(3, $transitions);

        $requestPaymentTransitionsCount = 0;
        foreach ($transitions as $transition) {
            if ($transition instanceof Definition && $transition->getArgument(0) === 'request_payment') {
                ++$requestPaymentTransitionsCount;
            }
        }

        $this->assertSame(2, $requestPaymentTransitionsCount);
    }

    public function testItDoesNotAddDuplicateTransitionsWhenReferenceResolvesToExistingDefinition(): void
    {
        $existingTransition = new Definition(Transition::class);
        $existingTransition->setArguments(['request_payment', ['paid'], ['awaiting_payment']]);
        $this->container->setDefinition('existing_transition_service', $existingTransition);

        $workflowDefinition = new Definition();
        $workflowDefinition->setArguments(['places', [new Reference('existing_transition_service')]]);
        $this->container->setDefinition('state_machine.sylius_order_payment.definition', $workflowDefinition);

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition('state_machine.sylius_order_payment.definition');
        $transitions = $definition->getArgument(1);

        $extra = self::isAuthorizedTransitionRequired() ? 1 : 0;
        $this->assertCount(2 + $extra, $transitions);

        $requestPaymentTransitionsCount = 0;
        foreach ($transitions as $transition) {
            if ($transition instanceof Definition && $transition->getArgument(0) === 'request_payment') {
                ++$requestPaymentTransitionsCount;
            }
        }

        $this->assertSame($extra, $requestPaymentTransitionsCount);
    }

    public function testItDoesNotAddDuplicateTransitionsWhenExistingTransitionUsesScalarFromAndTo(): void
    {
        $existingTransition = new Definition(Transition::class);
        $existingTransition->setArguments(['request_payment', 'paid', 'awaiting_payment']);

        $workflowDefinition = new Definition();
        $workflowDefinition->setArguments(['places', [$existingTransition]]);
        $this->container->setDefinition('state_machine.sylius_order_payment.definition', $workflowDefinition);

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition('state_machine.sylius_order_payment.definition');
        $transitions = $definition->getArgument(1);

        $extra = self::isAuthorizedTransitionRequired() ? 1 : 0;
        $this->assertCount(2 + $extra, $transitions);

        $requestPaymentTransitionsCount = 0;
        foreach ($transitions as $transition) {
            if ($transition instanceof Definition && $transition->getArgument(0) === 'request_payment') {
                ++$requestPaymentTransitionsCount;
            }
        }

        $this->assertSame(1 + $extra, $requestPaymentTransitionsCount);
    }

    public function testItDoesNotAddDuplicateTransitionsWhenExistingTransitionUsesArcDefinitions(): void
    {
        $existingTransition = new Definition(Transition::class);
        $existingTransition->setArguments([
            'request_payment',
            [new Definition(Arc::class, ['paid', 1])],
            [new Definition(Arc::class, ['awaiting_payment', 1])],
        ]);

        $workflowDefinition = new Definition();
        $workflowDefinition->setArguments(['places', [$existingTransition]]);
        $this->container->setDefinition('state_machine.sylius_order_payment.definition', $workflowDefinition);

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition('state_machine.sylius_order_payment.definition');
        $transitions = $definition->getArgument(1);

        $extra = self::isAuthorizedTransitionRequired() ? 1 : 0;
        $this->assertCount(2 + $extra, $transitions);

        $requestPaymentTransitionsCount = 0;
        foreach ($transitions as $transition) {
            if ($transition instanceof Definition && $transition->getArgument(0) === 'request_payment') {
                ++$requestPaymentTransitionsCount;
            }
        }

        $this->assertSame(1 + $extra, $requestPaymentTransitionsCount);
    }

    public function testItIgnoresReferenceToNonExistentDefinition(): void
    {
        $workflowDefinition = new Definition();
        $workflowDefinition->setArguments(['places', [new Reference('non_existent_transition_service')]]);
        $this->container->setDefinition('state_machine.sylius_order_payment.definition', $workflowDefinition);

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition('state_machine.sylius_order_payment.definition');
        $transitions = $definition->getArgument(1);

        $extra = self::isAuthorizedTransitionRequired() ? 1 : 0;
        $this->assertCount(3 + $extra, $transitions);
        $this->assertInstanceOf(Reference::class, $transitions[0]);

        $this->assertTransitionExists($transitions, 'request_payment', ['paid'], ['awaiting_payment']);
        $this->assertTransitionExists($transitions, 'cancel', ['paid'], ['cancelled']);

        if ($extra > 0) {
            $this->assertTransitionExists($transitions, 'request_payment', ['authorized'], ['awaiting_payment']);
        }
    }

    public function testItIgnoresNonDefinitionTransitions(): void
    {
        $workflowDefinition = new Definition();
        $workflowDefinition->setArguments(['places', ['not_a_definition', null]]);
        $this->container->setDefinition('state_machine.sylius_order_payment.definition', $workflowDefinition);

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition('state_machine.sylius_order_payment.definition');
        $transitions = $definition->getArgument(1);

        $extra = self::isAuthorizedTransitionRequired() ? 1 : 0;
        $this->assertCount(4 + $extra, $transitions);
        $this->assertSame('not_a_definition', $transitions[0]);
        $this->assertNull($transitions[1]);
    }

    public function testItIgnoresTransitionsWithInsufficientArguments(): void
    {
        $invalidTransition = new Definition(Transition::class);
        $invalidTransition->setArguments(['only_name']);

        $workflowDefinition = new Definition();
        $workflowDefinition->setArguments(['places', [$invalidTransition]]);
        $this->container->setDefinition('state_machine.sylius_order_payment.definition', $workflowDefinition);

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition('state_machine.sylius_order_payment.definition');
        $transitions = $definition->getArgument(1);

        $extra = self::isAuthorizedTransitionRequired() ? 1 : 0;
        $this->assertCount(3 + $extra, $transitions);
    }

    private static function isAuthorizedTransitionRequired(): bool
    {
        return version_compare(SyliusCoreBundle::VERSION, '2.2.7', '<');
    }

    private function assertTransitionExists(array $transitions, string $name, array $froms, array $tos): void
    {
        $found = false;

        foreach ($transitions as $transition) {
            if (!$transition instanceof Definition) {
                continue;
            }

            $arguments = $transition->getArguments();
            if (count($arguments) < 3) {
                continue;
            }

            [$transitionName, $transitionFroms, $transitionTos] = $arguments;

            if ($transitionName === $name && $transitionFroms === $froms && $transitionTos === $tos) {
                $found = true;

                break;
            }
        }

        $this->assertTrue($found, sprintf(
            'Transition "%s" with froms [%s] and tos [%s] not found',
            $name,
            implode(', ', $froms),
            implode(', ', $tos),
        ));
    }
}
