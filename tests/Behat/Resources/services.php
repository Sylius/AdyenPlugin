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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Client\AdyenTransportFactory;
use Tests\Sylius\AdyenPlugin\Behat\Service\HttpClientStub;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $container->import('services/contexts.yml');
    $container->import('services/mocker.yml');
    $container->import('services/pages.yml');

    $services->defaults()
        ->public();

    $services->set('tests.sylius_adyen.behat.context.api_mock_client', HttpClientStub::class);

    $services->set('sylius_adyen.client.adyen_transport_factory.decorator', AdyenTransportFactory::class)
        ->decorate('sylius_adyen.client.adyen_transport_factory')
        ->args([service('tests.sylius_adyen.behat.context.api_mock_client')]);
};
