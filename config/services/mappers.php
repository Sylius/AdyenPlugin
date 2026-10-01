<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Mapper\AdyenPaymentMethodsMapper;
use Sylius\AdyenPlugin\Mapper\PaymentMethodsMapperInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.mapper.payment_methods', AdyenPaymentMethodsMapper::class);

    $services->alias(PaymentMethodsMapperInterface::class, 'sylius_adyen.mapper.payment_methods');
};
