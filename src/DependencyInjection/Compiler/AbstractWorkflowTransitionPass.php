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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Transition;

abstract class AbstractWorkflowTransitionPass implements CompilerPassInterface
{
    private const WEIGHTED_ARC_CLASS = 'Symfony\\Component\\Workflow\\Arc';

    public function process(ContainerBuilder $container): void
    {
        $definitionId = $this->getWorkflowDefinitionId();
        if (!$container->hasDefinition($definitionId)) {
            return;
        }

        $definition = $container->getDefinition($definitionId);
        $transitions = $definition->getArgument(1);

        $existingTransitions = $this->extractExistingTransitions($container, $transitions);
        $updatedTransitions = $this->addMissingTransitions($transitions, $existingTransitions);

        $definition->setArgument(1, $updatedTransitions);
    }

    abstract protected function getWorkflowDefinitionId(): string;

    /** @return array<array{name: string, from: string, to: string}> */
    abstract protected function getRequiredTransitions(): array;

    /**
     * @param array<Definition|Reference> $transitions
     *
     * @return array<string>
     */
    private function extractExistingTransitions(ContainerBuilder $container, array $transitions): array
    {
        $existingTransitions = [];

        foreach ($transitions as $transition) {
            $transition = $this->resolveTransitionDefinition($container, $transition);
            if (null === $transition) {
                continue;
            }

            $arguments = $transition->getArguments();
            if (count($arguments) < 3) {
                continue;
            }

            [$name, $froms, $tos] = $arguments;
            foreach ((array) $froms as $from) {
                $from = $this->resolvePlaceName($from);
                if (null === $from) {
                    continue;
                }

                foreach ((array) $tos as $to) {
                    $to = $this->resolvePlaceName($to);
                    if (null === $to) {
                        continue;
                    }

                    $existingTransitions[] = $this->createTransitionKey($name, $from, $to);
                }
            }
        }

        return $existingTransitions;
    }

    private function resolveTransitionDefinition(ContainerBuilder $container, mixed $transition): ?Definition
    {
        if ($transition instanceof Reference) {
            $transitionId = (string) $transition;
            if (!$container->hasDefinition($transitionId)) {
                return null;
            }

            $transition = $container->getDefinition($transitionId);
        }

        return $transition instanceof Definition ? $transition : null;
    }

    private function resolvePlaceName(mixed $place): ?string
    {
        if (is_string($place)) {
            return $place;
        }

        if ($place instanceof Definition && self::WEIGHTED_ARC_CLASS === $place->getClass()) {
            return (string) $place->getArgument(0);
        }

        return null;
    }

    /**
     * @param array<Definition|Reference> $transitions
     * @param array<string> $existingTransitions
     *
     * @return array<Definition|Reference>
     */
    private function addMissingTransitions(array $transitions, array $existingTransitions): array
    {
        foreach ($this->getRequiredTransitions() as $requiredTransition) {
            $transitionKey = $this->createTransitionKey(
                $requiredTransition['name'],
                $requiredTransition['from'],
                $requiredTransition['to'],
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
