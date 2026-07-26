<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Form\Extension\PaymentTypeExtension;
use Sylius\AdyenPlugin\Form\Extension\ProductVariantTypeExtension;
use Sylius\AdyenPlugin\Form\Type\ConfigurationType;
use Sylius\AdyenPlugin\Form\Type\LoggerLevelFilterType;
use Sylius\AdyenPlugin\Grid\Filter\LoggerLevel;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.form.type.configuration', ConfigurationType::class)
        ->args([service('sylius_adyen.provider.esd_type')])
        ->tag('form.type')
        ->tag('sylius.gateway_configuration_type', ['type' => 'adyen', 'label' => 'sylius_adyen.ui.adyen_gateway_label']);

    $services->set('sylius_adyen.form.extension.payment_type', PaymentTypeExtension::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius.context.shopper'),
            service('sylius_adyen.provider.payment_methods'),
        ])
        ->tag('form.type_extension');

    $services->set('sylius_adyen.grid.filter.logger_level', LoggerLevel::class)
        ->tag('sylius.grid_filter', ['type' => 'adyen_log_level', 'form_type' => LoggerLevelFilterType::class]);

    $services->set('sylius_adyen.form.extension.product_variant', ProductVariantTypeExtension::class)
        ->tag('form.type_extension');
};
