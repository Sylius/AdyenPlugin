<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Callback\PreserveOrderTokenUponRedirectionCallback;
use Sylius\AdyenPlugin\Callback\RequestCancelCallback;
use Sylius\AdyenPlugin\Clearer\PaymentReferencesClearer;
use Sylius\AdyenPlugin\Controller\Shop\DropinConfigurationAction;
use Sylius\AdyenPlugin\Email\Sender\PaymentLinkEmailSender;
use Sylius\AdyenPlugin\Generator\PaymentLinkGenerator;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProvider;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProviderInterface;
use Sylius\AdyenPlugin\Twig\Extension\AdyenPaymentCheckerExtension;
use Sylius\AdyenPlugin\Twig\Extension\PaymentLinkGenerationCheckerExtension;
use Sylius\AdyenPlugin\Twig\Extension\ReverseEligibilityExtension;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $container->import('services/**/*.php');


    $services->defaults()
        ->public();

    $services->set('sylius_adyen.callback.request_cancel', RequestCancelCallback::class)
        ->args([
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius_abstraction.state_machine'),
            service('sylius.command_bus'),
        ]);

    $services->set('sylius_adyen.callback.preserve_order_token_upon_redirection', PreserveOrderTokenUponRedirectionCallback::class)
        ->args([service('request_stack')]);

    $services->set('sylius_adyen.controller.shop.dropin_configuration', DropinConfigurationAction::class)
        ->args([
            service('sylius_adyen.provider.dropin_configuration'),
            service('sylius.context.cart.composite'),
            service('sylius.repository.order'),
            service('router'),
        ])
        ->tag('controller.service_arguments');

    $services->set('sylius_adyen.provider.payment_methods', PaymentMethodsProvider::class)
        ->args([
            service('sylius_adyen.provider.adyen_client'),
            service('sylius_adyen.payment_methods_filter'),
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius_adyen.filter.stored_payment_methods'),
            service('sylius_adyen.mapper.payment_methods'),
            service('sylius_adyen.resolver.shopper_reference'),
            service('sylius_adyen.provider.current_shop_user'),
        ]);

    $services->alias(PaymentMethodsProviderInterface::class, 'sylius_adyen.provider.payment_methods');

    $services->set('sylius_adyen.clearer.payment_references', PaymentReferencesClearer::class)
        ->args([
            service('sylius_adyen.repository.adyen_reference'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('sylius_adyen.generator.payment_link', PaymentLinkGenerator::class)
        ->private()
        ->args([
            service('sylius_adyen.provider.adyen_client'),
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius_adyen.repository.payment_link'),
            service('sylius_adyen.factory.payment_link'),
            service('sylius_abstraction.state_machine'),
            service('doctrine.orm.entity_manager'),
            service('monolog.logger.adyen'),
        ]);

    $services->set('sylius_adyen.twig.extension.adyen_payment_checker', AdyenPaymentCheckerExtension::class)
        ->args([service('sylius_adyen.checker.adyen_payment_method')])
        ->tag('twig.extension');

    $services->set('sylius_adyen.twig.extension.payment_link_generation_checker', PaymentLinkGenerationCheckerExtension::class)
        ->args([service('sylius_adyen.checker.payment_pay_by_link_availability')])
        ->tag('twig.extension');

    $services->set('sylius_adyen.email_sender.payment_link', PaymentLinkEmailSender::class)
        ->args([service('sylius.email_sender')]);

    $services->set('sylius_adyen.twig.extension.reverse_eligibility', ReverseEligibilityExtension::class)
        ->args([service('sylius_adyen.checker.reverse_eligibility')])
        ->tag('twig.extension');
};
