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

namespace Sylius\AdyenPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const DEFAULT_LOGGER = 'logger';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sylius_adyen');

        $treeBuilder
            ->getRootNode()
            ->children()
                ->arrayNode('payment_methods')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('allowed_types')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('manual_capture_supporting_types')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                            ->performNoDeepMerging()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('logger')
                    ->treatTrueLike(self::DEFAULT_LOGGER)
                    ->defaultNull()
                ->end()
                ->arrayNode('esd')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('supported_currencies')
                            ->scalarPrototype()->end()
                            ->defaultValue(['USD'])
                        ->end()
                        ->arrayNode('supported_countries')
                            ->scalarPrototype()->end()
                            ->defaultValue(['US'])
                        ->end()
                        ->arrayNode('supported_card_brands')
                            ->scalarPrototype()->end()
                            ->defaultValue(['visa', 'mc'])
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('integrator_name')
                    ->defaultValue('Sylius')
                ->end()
        ;

        return $treeBuilder;
    }
}
