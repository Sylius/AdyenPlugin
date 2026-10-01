<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Logging\Monolog\DoctrineHandler;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.logging.monolog.doctrine_handler', DoctrineHandler::class)
        ->args([
            service('sylius_adyen.custom_factory.log'),
            service('sylius.repository.taxon'),
        ]);
};
