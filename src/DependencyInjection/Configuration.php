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

use Sylius\AdyenPlugin\Entity\AdyenPaymentDetail;
use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\AdyenPlugin\Entity\AdyenReference;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Entity\Log;
use Sylius\AdyenPlugin\Entity\LogInterface;
use Sylius\AdyenPlugin\Entity\PaymentLink;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\AdyenPlugin\Entity\ShopperReference;
use Sylius\AdyenPlugin\Entity\ShopperReferenceInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepository;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepository;
use Sylius\AdyenPlugin\Repository\ShopperReferenceRepository;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sylius_adyen');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $this->addResourcesSection($rootNode);
        $this->addPaymentMethodsSection($rootNode);
        $this->addEsdSection($rootNode);
        $this->addIntegratorNameSection($rootNode);

        return $treeBuilder;
    }

    private function addPaymentMethodsSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
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
                        ->arrayNode('only_for_logged_in_users_types')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                            ->performNoDeepMerging()
                        ->end()
                        ->arrayNode('only_manual_capture_types')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                            ->performNoDeepMerging()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function addEsdSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
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
        ;
    }

    private function addIntegratorNameSection(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->scalarNode('integrator_name')
                    ->defaultValue('Sylius')
                ->end()
            ->end()
        ;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('adyen_reference')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(AdyenReference::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(AdyenReferenceInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(AdyenReferenceRepository::class)->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('shopper_reference')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(ShopperReference::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(ShopperReferenceInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(ShopperReferenceRepository::class)->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('log')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Log::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(LogInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('adyen_payment_detail')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(AdyenPaymentDetail::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(AdyenPaymentDetailInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('payment_link')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(PaymentLink::class)->cannotBeEmpty()->end()
                                        ->scalarNode('interface')->defaultValue(PaymentLinkInterface::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(PaymentLinkRepository::class)->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
