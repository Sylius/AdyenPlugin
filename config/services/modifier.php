<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Modifier\ExpressCheckout\ApplePay\OrderAddressModifier;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\ApplePay\OrderAddressModifierInterface;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\GooglePay\OrderAddressModifier as GooglePayOrderAddressModifier;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\GooglePay\OrderAddressModifierInterface as GooglePayOrderAddressModifierInterface;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\OrderCustomerModifier;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\OrderCustomerModifierInterface;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\Paypal\OrderAddressModifier as PaypalOrderAddressModifier;
use Sylius\AdyenPlugin\Modifier\ExpressCheckout\Paypal\OrderAddressModifierInterface as PaypalOrderAddressModifierInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_adyen.modifier.express_checkout.apple_pay.order_address', OrderAddressModifier::class)
        ->args([service('sylius.factory.address')]);

    $services->alias(OrderAddressModifierInterface::class, 'sylius_adyen.modifier.express_checkout.apple_pay.order_address');

    $services->set('sylius_adyen.modifier.express_checkout.paypal.order_address', PaypalOrderAddressModifier::class)
        ->args([service('sylius.factory.address')]);

    $services->alias(PaypalOrderAddressModifierInterface::class, 'sylius_adyen.modifier.express_checkout.paypal.order_address');

    $services->set('sylius_adyen.modifier.express_checkout.google_pay.order_address', GooglePayOrderAddressModifier::class)
        ->args([service('sylius.factory.address')]);

    $services->alias(GooglePayOrderAddressModifierInterface::class, 'sylius_adyen.modifier.express_checkout.google_pay.order_address');

    $services->set('sylius_adyen.modifier.express_checkout.order_customer', OrderCustomerModifier::class)
        ->args([service('sylius.resolver.customer')]);

    $services->alias(OrderCustomerModifierInterface::class, 'sylius_adyen.modifier.express_checkout.order_customer');
};
