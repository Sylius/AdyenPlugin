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

    public const DEFAULT_PAYMENT_METHODS = [
        'scheme', 'dotpay', 'ideal', 'alipay', 'applepay', 'blik', 'amazonpay', 'sepadirectdebit',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sylius_adyen');

        $treeBuilder
            ->getRootNode()
            ->children()
                ->arrayNode('supported_types')
                    ->ignoreExtraKeys(false)
                    ->beforeNormalization()
                        ->always(static fn ($arg) => (array) $arg)
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

            ->end()
            ->beforeNormalization()
            ->always(static function ($arg) {
                $arg = (array) $arg;

                if (array_key_exists('supported_types', $arg)) {
                    return $arg;
                }

                $arg['supported_types'] = self::DEFAULT_PAYMENT_METHODS;

                return $arg;
            })
            ->end()
        ;

        return $treeBuilder;
    }
}
