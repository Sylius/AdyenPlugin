<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Provider\CurrentShopUserProvider;
use Sylius\AdyenPlugin\Provider\CurrentShopUserProviderInterface;
use Sylius\AdyenPlugin\Provider\DropinConfigurationProvider;
use Sylius\AdyenPlugin\Provider\DropinConfigurationProviderInterface;
use Sylius\AdyenPlugin\Provider\EsdTypeProvider;
use Sylius\AdyenPlugin\Provider\EsdTypeProviderInterface;
use Sylius\AdyenPlugin\Provider\Refund\OrderRefundedTotalProvider;
use Sylius\AdyenPlugin\Provider\Refund\SupportedRefundPaymentMethodsProvider;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.provider.current_shop_user', CurrentShopUserProvider::class)
        ->args([service('security.token_storage')]);

    $services->alias(CurrentShopUserProviderInterface::class, 'sylius_adyen.provider.current_shop_user');

    $services->set('sylius_adyen.provider.esd_type', EsdTypeProvider::class)
        ->args([tagged_iterator('sylius_adyen.esd.collector', indexAttribute: 'type')]);

    $services->alias(EsdTypeProviderInterface::class, 'sylius_adyen.provider.esd_type');

    $services->set('sylius_adyen.provider.refund_payment_methods', SupportedRefundPaymentMethodsProvider::class)
        ->decorate('sylius_refund.provider.refund_payment_methods')
        ->args([
            service('.inner'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.provider.order_refunded_total', OrderRefundedTotalProvider::class)
        ->decorate('sylius_refund.provider.order_refunded_total')
        ->args([
            service('.inner'),
            service('sylius_refund.repository.refund_payment'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.provider.dropin_configuration', DropinConfigurationProvider::class)
        ->args([
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius_adyen.provider.payment_methods'),
            service('sylius_adyen.provider.current_shop_user'),
            service('router'),
            service('translator'),
        ]);

    $services->alias(DropinConfigurationProviderInterface::class, 'sylius_adyen.provider.dropin_configuration');
};
