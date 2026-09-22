<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Client\AdyenClient;
use Sylius\AdyenPlugin\Client\AdyenTransportFactory;
use Sylius\AdyenPlugin\Client\ClientPayloadFactory;
use Sylius\AdyenPlugin\Client\PaypalUpdateOrderRequestFactory;
use Sylius\AdyenPlugin\Client\PaypalUpdateOrderRequestFactoryInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProvider;
use Sylius\AdyenPlugin\Provider\SignatureValidatorProvider;
use Sylius\AdyenPlugin\Validator\Constraint\AdyenCredentialsValidator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.provider.adyen_client', AdyenClientProvider::class)
        ->args([
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius.context.shopper'),
            service('sylius_adyen.client.adyen_transport_factory'),
            service('sylius_adyen.client.client_payload_factory'),
        ]);

    $services->set('sylius_adyen.client.client_payload_factory', ClientPayloadFactory::class)
        ->args([
            service('sylius_adyen.resolver.version.version'),
            service('serializer'),
            service('request_stack'),
            service('sylius_adyen.collector.esd.composite'),
            service('sylius_adyen.client.paypal_update_order_request_factory'),
        ]);

    $services->set('sylius_adyen.client.adyen_transport_factory', AdyenTransportFactory::class);

    $services->set('sylius_adyen.client.adyen', AdyenClient::class)
        ->factory([service('sylius_adyen.provider.adyen_client'), 'getDefaultClient']);

    $services->set('sylius_adyen.validator.adyen_credentials', AdyenCredentialsValidator::class)
        ->args([service('sylius_adyen.client.adyen_transport_factory')])
        ->tag('validator.constraint_validator', ['alias' => 'sylius_adyen.validator.credentials']);

    $services->set('sylius_adyen.provider.signature_validator', SignatureValidatorProvider::class)
        ->args([service('sylius_adyen.repository.query.adyen_payment_method')]);

    $services->set('sylius_adyen.client.paypal_update_order_request_factory', PaypalUpdateOrderRequestFactory::class)
        ->args([
            service('sylius.resolver.shipping_methods'),
            service('sylius.registry.shipping_calculator'),
        ]);

    $services->alias(PaypalUpdateOrderRequestFactoryInterface::class, 'sylius_adyen.client.paypal_update_order_request_factory');
};
