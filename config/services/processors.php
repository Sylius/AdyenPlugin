<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Processor\Order\ReverseOrderPaymentProcessor;
use Sylius\AdyenPlugin\Processor\Order\UpdateOrderPaymentStateProcessor;
use Sylius\AdyenPlugin\Processor\Payment\AuthorizationStateProcessor;
use Sylius\AdyenPlugin\Processor\Payment\ManualCaptureProcessor;
use Sylius\AdyenPlugin\Processor\Refund\RefundPaymentStateProcessor;
use Sylius\Component\Core\OrderProcessing\OrderPaymentProcessor;
use Sylius\Component\Order\Model\OrderInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $parameters->set('sylius_adyen.order_payment_processor.after_checkout.fail.unsupported_states', [OrderInterface::STATE_CANCELLED]);

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.processor.authorization_state', AuthorizationStateProcessor::class)
        ->args([
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius_abstraction.state_machine'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('sylius_adyen.processor.order.reverse_payment', ReverseOrderPaymentProcessor::class)
        ->args([
            service('sylius.command_bus'),
            service('sylius_abstraction.state_machine'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.processor.order.update_order_payment_state', UpdateOrderPaymentStateProcessor::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.processor.refund.refund_payment_state', RefundPaymentStateProcessor::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.processor.manual_capture', ManualCaptureProcessor::class)
        ->args([
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius.command_bus'),
            service('sylius_abstraction.state_machine'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('sylius_adyen.order_processing.order_payment_processor.after_checkout.fail', OrderPaymentProcessor::class)
        ->args([
            service('sylius.provider.payment.order'),
            service('sylius.remover.payment.order'),
            '%sylius_adyen.order_payment_processor.after_checkout.fail.unsupported_states%',
            'new',
        ]);

    $services->set('sylius_adyen.order_processing.order_payment_processor.checkout', \Sylius\AdyenPlugin\Processor\Order\OrderPaymentProcessor::class)
        ->decorate('sylius.order_processing.order_payment_processor.checkout')
        ->args([
            service('.inner'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.order_processing.order_payment_processor.after_checkout', \Sylius\AdyenPlugin\Processor\Order\OrderPaymentProcessor::class)
        ->decorate('sylius.order_processing.order_payment_processor.after_checkout')
        ->args([
            service('.inner'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);
};
