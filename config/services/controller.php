<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Controller\Admin\CaptureOrderPaymentAction;
use Sylius\AdyenPlugin\Controller\Admin\GeneratePayLinkAction;
use Sylius\AdyenPlugin\Controller\Admin\ReverseOrderPaymentAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\AddToNewCartAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\CartConfigurationAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\GooglePay\CheckoutAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\GooglePay\ShippingOptionsAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\PayPal\InitializeAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\PayPal\ShippingAddressChangeAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\PayPal\ShippingOptionsChangeAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\ProductConfigurationAction;
use Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\RemoveCartAction;
use Sylius\Component\Resource\Metadata\MetadataInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public()
        ->tag('controller.service_arguments');

    $services->set('sylius_adyen.controller.shop.express_checkout.cart_configuration', CartConfigurationAction::class)
        ->args([
            tagged_iterator('sylius_adyen.express_checkout.cart.configuration', indexAttribute: 'key'),
            service('sylius.context.cart.composite'),
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius_adyen.provider.payment_methods'),
            service('sylius_adyen.provider.express_checkout.country'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.product_configuration', ProductConfigurationAction::class)
        ->args([
            tagged_iterator('sylius_adyen.express_checkout.cart.configuration', indexAttribute: 'key'),
            service('sylius.context.cart.new'),
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius_adyen.provider.payment_methods'),
            service('sylius_adyen.provider.express_checkout.country'),
            service('router'),
            service('sylius.repository.product'),
            service('sylius.factory.order_item'),
            service('sylius.modifier.order_item_quantity'),
            service('sylius.modifier.order'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.add_to_new_cart', AddToNewCartAction::class)
        ->args([
            service('sylius.factory.add_to_cart_command'),
            service('sylius.context.cart.new'),
            service('doctrine.orm.entity_manager'),
            service('sylius.factory.order_item'),
            service('form.factory'),
            inline_service(MetadataInterface::class)
                ->args(['sylius.order_item'])
                ->factory([service('sylius.resource_registry'), 'get']),
            service('sylius.resource_controller.new_resource_factory'),
            service('sylius.modifier.order_item_quantity'),
            service('sylius.modifier.order'),
            service('sylius.resource_controller.request_configuration_factory'),
            service('sylius.assigner.order_token.unique_id_based'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.remove_cart', RemoveCartAction::class)
        ->args([service('sylius.repository.order')]);

    $services->set('sylius_adyen.controller.shop.express_checkout.google_pay.shipping_options', ShippingOptionsAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius.manager.order'),
            service('sylius.repository.shipping_method'),
            service('sylius.order_processing.order_processor'),
            service('sylius_adyen.modifier.express_checkout.google_pay.order_address'),
            service('sylius_adyen.provider.express_checkout.google_pay.transaction_info'),
            service('sylius_adyen.provider.express_checkout.google_pay.shipping_option_parameters'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.google_pay.checkout', CheckoutAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius_adyen.modifier.express_checkout.google_pay.order_address'),
            service('sylius_adyen.modifier.express_checkout.order_customer'),
            service('sylius_adyen.resolver.order.express_checkout.checkout'),
            service('sylius_adyen.checker.order_checkout_complete_integrity'),
        ]);

    $services->set('sylius_adyen.controller.admin.payment.generate_pay_link', GeneratePayLinkAction::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius_adyen.generator.payment_link'),
            service('sylius_adyen.email_sender.payment_link'),
            service('router'),
            service('request_stack'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.paypal.initialize', InitializeAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius.command_bus'),
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius.repository.payment'),
            service('sylius_adyen.provider.adyen_client'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.paypal.shipping_address_change', ShippingAddressChangeAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius_adyen.provider.adyen_client'),
            service('sylius.order_processing.order_processor'),
            service('sylius.manager.order'),
            service('sylius_adyen.modifier.express_checkout.paypal.order_address'),
            service('sylius.command_bus'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.paypal.shipping_options_change', ShippingOptionsChangeAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius_adyen.provider.adyen_client'),
            service('sylius.repository.shipping_method'),
            service('sylius.order_processing.order_processor'),
            service('sylius.manager.order'),
            service('sylius.command_bus'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.paypal.checkout', \Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\PayPal\CheckoutAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius_adyen.modifier.express_checkout.paypal.order_address'),
            service('sylius_adyen.modifier.express_checkout.order_customer'),
            service('sylius_adyen.resolver.order.express_checkout.checkout'),
            service('sylius_adyen.checker.order_checkout_complete_integrity'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.apple_pay.shipping_address_change', \Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\ApplePay\ShippingAddressChangeAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius.manager.order'),
            service('sylius.order_processing.order_processor'),
            service('sylius_adyen.modifier.express_checkout.apple_pay.order_address'),
            service('sylius_adyen.provider.express_checkout.apple_pay.transaction_info'),
            service('sylius_adyen.provider.express_checkout.apple_pay.shipping_methods'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.apple_pay.shipping_options_change', \Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\ApplePay\ShippingOptionsChangeAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius.manager.order'),
            service('sylius.order_processing.order_processor'),
            service('sylius.repository.shipping_method'),
            service('sylius_adyen.provider.express_checkout.apple_pay.transaction_info'),
            service('sylius_adyen.provider.express_checkout.apple_pay.shipping_methods'),
        ]);

    $services->set('sylius_adyen.controller.shop.express_checkout.apple_pay.checkout', \Sylius\AdyenPlugin\Controller\Shop\ExpressCheckout\ApplePay\CheckoutAction::class)
        ->args([
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius_adyen.modifier.express_checkout.apple_pay.order_address'),
            service('sylius_adyen.modifier.express_checkout.order_customer'),
            service('sylius_adyen.resolver.order.express_checkout.checkout'),
            service('sylius_adyen.checker.order_checkout_complete_integrity'),
        ]);

    $services->set('sylius_adyen.controller.admin.order_payment.reverse', ReverseOrderPaymentAction::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius_adyen.processor.order.reverse_payment'),
            service('request_stack'),
            service('router'),
        ]);

    $services->set('sylius_adyen.controller.admin.order_payment.capture', CaptureOrderPaymentAction::class)
        ->args([
            service('sylius.repository.payment'),
            service('sylius_adyen.processor.manual_capture'),
            service('router'),
            service('request_stack'),
            service('monolog.logger.adyen'),
        ]);
};
