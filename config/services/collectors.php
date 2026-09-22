<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Collector\CompositeEsdCollector;
use Sylius\AdyenPlugin\Collector\CompositeEsdCollectorInterface;
use Sylius\AdyenPlugin\Collector\ItemDetailLineCollector;
use Sylius\AdyenPlugin\Collector\ItemDetailLineCollectorInterface;
use Sylius\AdyenPlugin\Collector\Level2EsdCollector;
use Sylius\AdyenPlugin\Collector\Level3EsdCollector;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.collector.esd.level2', Level2EsdCollector::class)
        ->tag('sylius_adyen.esd.collector', ['priority' => 0, 'type' => 'level2']);

    $services->set('sylius_adyen.collector.esd.level3', Level3EsdCollector::class)
        ->args([
            service('sylius_adyen.collector.esd.level2'),
            service('sylius_adyen.collector.esd.item_detail_line'),
        ])
        ->tag('sylius_adyen.esd.collector', ['priority' => 100, 'type' => 'level3']);

    $services->set('sylius_adyen.collector.esd.composite', CompositeEsdCollector::class)
        ->args([
            tagged_iterator('sylius_adyen.esd.collector', indexAttribute: 'type'),
            '%sylius_adyen.esd.supported_currencies%',
            '%sylius_adyen.esd.supported_countries%',
            service('sylius_adyen.checker.esd_card_payment_support'),
        ]);

    $services->alias(CompositeEsdCollectorInterface::class, 'sylius_adyen.collector.esd.composite');

    $services->set('sylius_adyen.collector.esd.item_detail_line', ItemDetailLineCollector::class);

    $services->alias(ItemDetailLineCollectorInterface::class, 'sylius_adyen.collector.esd.item_detail_line');
};
