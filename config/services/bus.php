<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Bus\Handler\AlterPaymentHandler;
use Sylius\AdyenPlugin\Bus\Handler\AuthorizePaymentByLinkHandler;
use Sylius\AdyenPlugin\Bus\Handler\CreatePaymentDetailForPaymentHandler;
use Sylius\AdyenPlugin\Bus\Handler\CreateReferenceForPaymentHandler;
use Sylius\AdyenPlugin\Bus\Handler\CreateReferenceForRefundHandler;
use Sylius\AdyenPlugin\Bus\Handler\PaymentFinalizationHandler;
use Sylius\AdyenPlugin\Bus\Handler\PaymentRefundedHandler;
use Sylius\AdyenPlugin\Bus\Handler\PaymentStatusReceivedHandler;
use Sylius\AdyenPlugin\Bus\Handler\PrepareOrderForPaymentHandler;
use Sylius\AdyenPlugin\Bus\Handler\RefundPaymentGeneratedHandler;
use Sylius\AdyenPlugin\Bus\Handler\RefundPaymentHandler;
use Sylius\AdyenPlugin\Bus\Handler\ReversePaymentHandler;
use Sylius\AdyenPlugin\Bus\Handler\TakeOverPaymentHandler;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactory;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolver;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.bus.handler.authorize_payment', PaymentFinalizationHandler::class)
        ->args([service('sylius_abstraction.state_machine')])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.payment_status_received', PaymentStatusReceivedHandler::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius.repository.payment'),
            service('sylius.repository.order'),
            service('sylius.command_bus'),
            service('sylius_adyen.bus.payment_command_factory'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.resolver.payment.event_code', EventCodeResolver::class);

    $services->set('sylius_adyen.bus.payment_command_factory', PaymentCommandFactory::class)
        ->args([service('sylius_adyen.resolver.payment.event_code')]);

    $services->set('sylius_adyen.bus.handler.take_over_payment', TakeOverPaymentHandler::class)
        ->args([
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius_adyen.clearer.payment_references'),
            service('sylius.manager.payment'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.request_capture', AlterPaymentHandler::class)
        ->args([
            service('sylius_adyen.provider.adyen_client'),
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius_abstraction.state_machine'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.refund_payment_generated', RefundPaymentGeneratedHandler::class)
        ->args([
            service('sylius_adyen.provider.adyen_client'),
            service('sylius.repository.payment'),
            service('sylius.repository.payment_method'),
            service('sylius_refund.repository.refund_payment'),
            service('sylius.command_bus'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.event_bus']);

    $services->set('sylius_adyen.bus.handler.refund_payment', RefundPaymentHandler::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius_refund.manager.refund_payment'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.payment_refunded', PaymentRefundedHandler::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius_refund.factory.refund_payment'),
            service('sylius_adyen.factory.adyen_reference'),
            service('sylius_adyen.repository.adyen_reference'),
            service('doctrine.orm.entity_manager'),
            service('monolog.logger.adyen'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.create_reference_for_payment', CreateReferenceForPaymentHandler::class)
        ->args([
            service('sylius_adyen.repository.adyen_reference'),
            service('sylius_adyen.custom_factory.adyen_reference'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.create_reference_for_refund', CreateReferenceForRefundHandler::class)
        ->args([
            service('sylius_adyen.repository.adyen_reference'),
            service('sylius_adyen.custom_factory.adyen_reference'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.prepare_order_for_payment', PrepareOrderForPaymentHandler::class)
        ->args([
            service('sylius.number_assigner.order_number'),
            service('sylius.repository.order'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.reverse_payment', ReversePaymentHandler::class)
        ->args([
            service('sylius_adyen.provider.adyen_client'),
            service('sylius_abstraction.state_machine'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.create_payment_detail_for_payment', CreatePaymentDetailForPaymentHandler::class)
        ->args([
            service('sylius_adyen.repository.adyen_payment_detail'),
            service('sylius_adyen.custom_factory.adyen_payment_detail'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);

    $services->set('sylius_adyen.bus.handler.authorize_payment_by_link', AuthorizePaymentByLinkHandler::class)
        ->args([
            service('sylius_adyen.repository.payment_link'),
            service('serializer'),
            service('doctrine.orm.entity_manager'),
            service('sylius.command_bus'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius.command_bus']);
};
