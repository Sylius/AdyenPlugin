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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Workflow\Transition;

final class AddPaymentWorkflowTransitionPass implements CompilerPassInterface
{
    private const REQUIRED_TRANSITIONS = [
        ['name' => 'process', 'from' => 'authorized', 'to' => 'processing'],
        ['name' => 'fail', 'from' => 'completed', 'to' => 'failed'],
        ['name' => 'cancel', 'from' => 'processing_reversal', 'to' => 'cancelled'],
        ['name' => 'refund', 'from' => 'processing_reversal', 'to' => 'refunded'],
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('state_machine.sylius_payment.definition')) {
            return;
        }

        $definition = $container->getDefinition('state_machine.sylius_payment.definition');
        $transitions = $definition->getArgument(1);

        $existingTransitions = $this->extractExistingTransitions($transitions);
        $updatedTransitions = $this->addMissingTransitions($transitions, $existingTransitions);

        $definition->setArgument(1, $updatedTransitions);
    }

    /**
     * @param array<Definition> $transitions
     *
     * @return array<string>
     */
    private function extractExistingTransitions(array $transitions): array
    {
        $existingTransitions = [];

        foreach ($transitions as $transition) {
            if (!$transition instanceof Definition) {
                continue;
            }

            $arguments = $transition->getArguments();
            if (count($arguments) < 3) {
                continue;
            }

            [$name, $froms, $tos] = $arguments;
            foreach ($froms as $from) {
                foreach ($tos as $to) {
                    $existingTransitions[] = $this->createTransitionKey($name, $from, $to);
                }
            }
        }

        return $existingTransitions;
    }

    /**
     * @param array<Definition> $transitions
     * @param array<string> $existingTransitions
     *
     * @return array<Definition>
     */
    private function addMissingTransitions(array $transitions, array $existingTransitions): array
    {
        foreach (self::REQUIRED_TRANSITIONS as $requiredTransition) {
            $transitionKey = $this->createTransitionKey(
                $requiredTransition['name'],
                $requiredTransition['from'],
                $requiredTransition['to']
            );

            if (!in_array($transitionKey, $existingTransitions, true)) {
                $transitions[] = $this->createTransitionDefinition($requiredTransition);
            }
        }

        return $transitions;
    }

    private function createTransitionKey(string $name, string $from, string $to): string
    {
        return sprintf('%s:%s:%s', $name, $from, $to);
    }

    /** @param array{name: string, from: string, to: string} $transitionConfig */
    private function createTransitionDefinition(array $transitionConfig): Definition
    {
        $transition = new Definition(Transition::class);
        $transition->setArguments([
            $transitionConfig['name'],
            [$transitionConfig['from']],
            [$transitionConfig['to']],
        ]);

        return $transition;
    }
}
