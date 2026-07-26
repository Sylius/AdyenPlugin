<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\EventSubscriber\FilterHttpAuthenticationForNotificationProcessor;
use Sylius\AdyenPlugin\Filter\CompositePaymentMethodsFilter;
use Sylius\AdyenPlugin\Filter\ConfiguredPaymentMethodsFilter;
use Sylius\AdyenPlugin\Filter\GuestPaymentMethodsFilter;
use Sylius\AdyenPlugin\Filter\ManualCapturePaymentMethodsFilter;
use Sylius\AdyenPlugin\Filter\PaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Filter\StoredPaymentMethodsFilter;
use Sylius\AdyenPlugin\Filter\StoredPaymentMethodsFilterInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.payment_methods_filter', CompositePaymentMethodsFilter::class)
        ->args([tagged_iterator('sylius_adyen.payment_methods_filter')]);

    $services->alias(PaymentMethodsFilterInterface::class, 'sylius_adyen.payment_methods_filter');

    $services->set('sylius_adyen.payment_methods_filter.configured', ConfiguredPaymentMethodsFilter::class)
        ->args(['%sylius_adyen.payment_methods.allowed_types%'])
        ->tag('sylius_adyen.payment_methods_filter');

    $services->set('sylius_adyen.payment_methods_filter.manual_capture', ManualCapturePaymentMethodsFilter::class)
        ->args(['%sylius_adyen.payment_methods.manual_capture_supporting_types%'])
        ->tag('sylius_adyen.payment_methods_filter');

    $services->set('sylius_adyen.payment_methods_filter.guest', GuestPaymentMethodsFilter::class)
        ->args(['%sylius_adyen.payment_methods.only_for_logged_in_users_types%'])
        ->tag('sylius_adyen.payment_methods_filter');

    $services->set('sylius_adyen.filter.stored_payment_methods', StoredPaymentMethodsFilter::class);

    $services->alias(StoredPaymentMethodsFilterInterface::class, 'sylius_adyen.filter.stored_payment_methods');

    $services->set('sylius_adyen.event_subscriber.filter_http_authentication_for_notification_processor', FilterHttpAuthenticationForNotificationProcessor::class)
        ->args([
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('monolog.logger.adyen'),
        ])
        ->tag('kernel.event_subscriber');
};
