<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\EventListener\Workflow\Order\GuardCancelOrderListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Order\RequestCancelOnCancelListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Order\ReversePaymentOnCancelListener;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_adyen.listener.workflow.order.reverse_payment_on_cancel', ReversePaymentOnCancelListener::class)
        ->args([service('sylius_adyen.processor.order.reverse_payment')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_order.completed.cancel', 'priority' => 700]);

    $services->set('sylius_adyen.listener.workflow.order.request_cancel_on_cancel', RequestCancelOnCancelListener::class)
        ->args([service('sylius_adyen.callback.request_cancel')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_order.completed.cancel', 'priority' => 600]);

    $services->set('sylius_adyen.listener.workflow.order.guard_cancel', GuardCancelOrderListener::class)
        ->args([service('sylius_adyen.state_machine.guard.order')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_order.guard.cancel', 'priority' => 100]);
};
