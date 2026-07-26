<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\EventListener\Workflow\OrderPayment\GuardCancelListener;
use Sylius\AdyenPlugin\EventListener\Workflow\OrderPayment\GuardRequestPaymentListener;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_adyen.listener.workflow.order_payment.guard_cancel', GuardCancelListener::class)
        ->args([service('sylius_adyen.state_machine.guard.order_payment')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_order_payment.guard.cancel', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.order_payment.guard_request_payment', GuardRequestPaymentListener::class)
        ->args([service('sylius_adyen.state_machine.guard.order_payment')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_order_payment.guard.request_payment', 'priority' => 100]);
};
