<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Api\OpenApi\AdyenDropinConfigurationDocumentationModifier;
use Sylius\AdyenPlugin\Api\OpenApi\AdyenPaymentDetailsDocumentationModifier;
use Sylius\AdyenPlugin\Api\OpenApi\AdyenThankYouDocumentationModifier;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.api.shop.open_api.documentation_modifier.dropin_configuration', AdyenDropinConfigurationDocumentationModifier::class)
        ->args(['%sylius.security.api_shop_route%'])
        ->tag('sylius.open_api.modifier');

    $services->set('sylius_adyen.api.shop.open_api.documentation_modifier.thank_you', AdyenThankYouDocumentationModifier::class)
        ->args(['%sylius.security.api_shop_route%'])
        ->tag('sylius.open_api.modifier');

    $services->set('sylius_adyen.api.shop.open_api.documentation_modifier.payment_details', AdyenPaymentDetailsDocumentationModifier::class)
        ->args(['%sylius.security.api_shop_route%'])
        ->tag('sylius.open_api.modifier');
};
