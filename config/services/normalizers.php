<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Normalizer\AdditionalDetailsNormalizer;
use Sylius\AdyenPlugin\Normalizer\AddressNormalizer;
use Sylius\AdyenPlugin\Normalizer\OrderItemToLineItemNormalizer;
use Sylius\AdyenPlugin\Normalizer\ShippingLineGenerator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.normalizer.additional_details', AdditionalDetailsNormalizer::class)
        ->args([
            service('request_stack'),
            service('sylius_adyen.normalizer.shipping_line_generator'),
        ])
        ->tag('serializer.normalizer', ['priority' => 100]);

    $services->set('sylius_adyen.normalizer.address', AddressNormalizer::class)
        ->args([service('sylius_adyen.resolver.address.street_address')])
        ->tag('serializer.normalizer', ['priority' => 100]);

    $services->set('sylius_adyen.normalizer.order_item_to_line_item', OrderItemToLineItemNormalizer::class)
        ->args([
            service('request_stack'),
            service('router'),
            service('sylius_adyen.resolver.product.thumbnail_url'),
            '%locale%',
        ])
        ->tag('serializer.normalizer', ['priority' => 100]);

    $services->set('sylius_adyen.normalizer.shipping_line_generator', ShippingLineGenerator::class)
        ->args([service('translator')]);
};
