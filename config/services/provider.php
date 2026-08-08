<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\ShippingMethodsProvider;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\ShippingMethodsProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\Cart\ApplePayConfigurationProvider;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\Cart\GooglePayConfigurationProvider;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\Cart\PaypalConfigurationProvider;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\CountryProvider;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\CountryProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\ShippingOptionParametersProvider;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\ShippingOptionParametersProviderInterface;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\TransactionInfoProvider;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay\TransactionInfoProviderInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_adyen.provider.express_checkout.cart.google_pay_configuration', GooglePayConfigurationProvider::class)
        ->args([
            service('router'),
            service('sylius_adyen.provider.express_checkout.google_pay.transaction_info'),
        ])
        ->tag('sylius_adyen.express_checkout.cart.configuration', ['key' => 'googlePay']);

    $services->set('sylius_adyen.provider.express_checkout.cart.paypal_configuration', PaypalConfigurationProvider::class)
        ->args([service('router')])
        ->tag('sylius_adyen.express_checkout.cart.configuration', ['key' => 'paypal']);

    $services->set('sylius_adyen.provider.express_checkout.cart.apple_pay_configuration', ApplePayConfigurationProvider::class)
        ->args([service('router')])
        ->tag('sylius_adyen.express_checkout.cart.configuration', ['key' => 'applePay']);

    $services->set('sylius_adyen.provider.express_checkout.google_pay.transaction_info', TransactionInfoProvider::class)
        ->args([service('translator')]);

    $services->alias(TransactionInfoProviderInterface::class, 'sylius_adyen.provider.express_checkout.google_pay.transaction_info');

    $services->set('sylius_adyen.provider.express_checkout.google_pay.shipping_option_parameters', ShippingOptionParametersProvider::class)
        ->args([
            service('sylius.resolver.shipping_methods'),
            service('sylius.registry.shipping_calculator'),
            service('sylius.formatter.money'),
        ]);

    $services->alias(ShippingOptionParametersProviderInterface::class, 'sylius_adyen.provider.express_checkout.google_pay.shipping_option_parameters');

    $services->set('sylius_adyen.provider.express_checkout.apple_pay.transaction_info', \Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\TransactionInfoProvider::class)
        ->args([service('translator')]);

    $services->alias(\Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\TransactionInfoProviderInterface::class, 'sylius_adyen.provider.express_checkout.apple_pay.transaction_info');

    $services->set('sylius_adyen.provider.express_checkout.apple_pay.shipping_methods', ShippingMethodsProvider::class)
        ->args([
            service('sylius.resolver.shipping_methods'),
            service('sylius.registry.shipping_calculator'),
        ]);

    $services->alias(ShippingMethodsProviderInterface::class, 'sylius_adyen.provider.express_checkout.apple_pay.shipping_methods');

    $services->set('sylius_adyen.provider.express_checkout.country', CountryProvider::class)
        ->args([service('sylius.repository.country')]);

    $services->alias(CountryProviderInterface::class, 'sylius_adyen.provider.express_checkout.country');
};
