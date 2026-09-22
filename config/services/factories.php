<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Factory\AdyenPaymentDetailFactory;
use Sylius\AdyenPlugin\Factory\AdyenReferenceFactory;
use Sylius\AdyenPlugin\Factory\LogFactory;
use Sylius\AdyenPlugin\Factory\PaymentLinkFactory;
use Sylius\AdyenPlugin\Factory\ShopperReferenceFactory;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.custom_factory.shopper_reference', ShopperReferenceFactory::class)
        ->decorate('sylius_adyen.factory.shopper_reference')
        ->args([service('.inner')]);

    $services->set('sylius_adyen.custom_factory.adyen_reference', AdyenReferenceFactory::class)
        ->decorate('sylius_adyen.factory.adyen_reference')
        ->args([service('.inner')]);

    $services->set('sylius_adyen.custom_factory.log', LogFactory::class)
        ->decorate('sylius_adyen.factory.log')
        ->args([service('.inner')]);

    $services->set('sylius_adyen.custom_factory.adyen_payment_detail', AdyenPaymentDetailFactory::class)
        ->decorate('sylius_adyen.factory.adyen_payment_detail')
        ->args([
            service('.inner'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.custom_factory.payment_link', PaymentLinkFactory::class)
        ->decorate('sylius_adyen.factory.payment_link');
};
