<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Repository\Query\AdyenPaymentMethodQuery;
use Sylius\AdyenPlugin\Repository\Query\AdyenPaymentMethodQueryInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.repository.query.adyen_payment_method', AdyenPaymentMethodQuery::class)
        ->args([service('sylius.repository.payment_method')]);

    $services->alias(AdyenPaymentMethodQueryInterface::class, 'sylius_adyen.repository.query.adyen_payment_method');
};
