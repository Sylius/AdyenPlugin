<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Controller\Shop\AdyenDetailsAction;
use Sylius\AdyenPlugin\Controller\Shop\PaymentDetailsAction;
use Sylius\AdyenPlugin\Controller\Shop\PaymentsAction;
use Sylius\AdyenPlugin\Controller\Shop\ProcessNotificationsAction;
use Sylius\AdyenPlugin\Controller\Shop\RedirectTargetAction;
use Sylius\AdyenPlugin\Controller\Shop\RemoveStoredTokenAction;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.controller.shop.payment_details', PaymentDetailsAction::class)
        ->args([
            service('sylius_adyen.provider.adyen_client'),
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius_adyen.processor.payment_response'),
            service('sylius_adyen.repository.adyen_payment_detail'),
            service('sylius_adyen.checker.order_checkout_complete_integrity'),
        ])
        ->tag('controller.service_arguments');

    $services->set('sylius_adyen.controller.shop.payments', PaymentsAction::class)
        ->args([
            service('sylius_adyen.provider.adyen_client'),
            service('router'),
            service('sylius_adyen.resolver.order.payment_checkout_order'),
            service('sylius_adyen.processor.payment_response'),
            service('sylius_adyen.clearer.payment_references'),
            service('sylius_adyen.resolver.shopper_reference'),
            service('sylius_adyen.provider.current_shop_user'),
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius_adyen.checker.order_checkout_complete_integrity'),
            service('sylius.command_bus'),
        ])
        ->tag('controller.service_arguments');

    $services->set('sylius_adyen.controller.shop.process_notifications', ProcessNotificationsAction::class)
        ->args([
            service('sylius_adyen.resolver.notification.notification_command'),
            service('sylius_adyen.resolver.notification.notification'),
            service('monolog.logger.adyen'),
            service('sylius.command_bus'),
        ])
        ->tag('controller.service_arguments');

    $services->set('sylius_adyen.controller.shop.redirect_target', RedirectTargetAction::class)
        ->args([
            service('sylius_adyen.processor.payment_response'),
            service('sylius_adyen.resolver.payment.payment_details'),
        ])
        ->tag('controller.service_arguments');

    $services->set('sylius_adyen.controller.shop.remove_stored_token', RemoveStoredTokenAction::class)
        ->args([
            service('security.token_storage'),
            service('sylius_adyen.repository.shopper_reference'),
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius_adyen.provider.adyen_client'),
        ])
        ->tag('controller.service_arguments');

    $services->set('sylius_adyen.controller.shop.adyen_details', AdyenDetailsAction::class)
        ->args([service('sylius_adyen.resolver.payment.payment_details')])
        ->tag('controller.service_arguments');
};
