<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Validator\Constraint\HmacSignatureValidator;
use Sylius\AdyenPlugin\Validator\Constraint\ProvinceAddressConstraintValidatorDecorator;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $parameters->set('sylius_adyen.checkout.addressing.countries_requiring_province', ['CA', 'US']);

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.validator.hmac_signature', HmacSignatureValidator::class)
        ->args([
            service('sylius_adyen.provider.signature_validator'),
            service('property_accessor'),
            service('serializer'),
        ])
        ->tag('validator.constraint_validator');

    $services->set('sylius_adyen.validator.province_address_constraint_decorator', ProvinceAddressConstraintValidatorDecorator::class)
        ->decorate('sylius.validator.valid_province_address')
        ->args([
            service('.inner'),
            '%sylius_adyen.checkout.addressing.countries_requiring_province%',
            service('sylius.context.channel'),
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius.context.cart'),
        ]);
};
