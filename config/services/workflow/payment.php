<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\EventListener\Workflow\Payment\AutoCaptureOnAuthorizeListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\FlushOnFailListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\GuardAuthorizeListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\GuardCancelListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\GuardCompleteListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\ProcessFailedPaymentOrderListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\ProcessOrderPaymentAfterReversalListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\ResolveFailedPaymentStateListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\ResolveStateListener;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_adyen.listener.workflow.payment.guard_complete', GuardCompleteListener::class)
        ->args([service('sylius_adyen.state_machine.guard.payment')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.guard.complete', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.payment.guard_authorize', GuardAuthorizeListener::class)
        ->args([service('sylius_adyen.state_machine.guard.payment')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.guard.authorize', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.payment.guard_cancel', GuardCancelListener::class)
        ->args([service('sylius_adyen.state_machine.guard.payment')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.guard.cancel', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.payment.auto_capture_on_authorize', AutoCaptureOnAuthorizeListener::class)
        ->args([service('sylius_adyen.processor.authorization_state')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.authorize', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.payment.process_order_payment_after_reversal', ProcessOrderPaymentAfterReversalListener::class)
        ->args([service('sylius_adyen.processor.order.update_order_payment_state')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.cancel', 'priority' => 100])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.refund', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.payment.resolve_state', ResolveStateListener::class)
        ->args([service('sylius.state_resolver.order_payment')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.complete', 'priority' => 100])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.process', 'priority' => 100])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.refund', 'priority' => 100])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.authorize', 'priority' => 100])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.capture', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.payment.process_failed_payment_order', ProcessFailedPaymentOrderListener::class)
        ->args([service('sylius_adyen.order_processing.order_payment_processor.after_checkout.fail')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.fail', 'priority' => 300]);

    $services->set('sylius_adyen.listener.workflow.payment.flush_on_fail', FlushOnFailListener::class)
        ->args([service('doctrine.orm.entity_manager')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.fail', 'priority' => 200]);

    $services->set('sylius_adyen.listener.workflow.payment.resolve_failed_payment_state', ResolveFailedPaymentStateListener::class)
        ->args([service('sylius_adyen.state_resolver.order_failed_payment')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_payment.completed.fail', 'priority' => 100]);
};
