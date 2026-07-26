<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\EventListener\Workflow\Refund\GuardRefundPaymentCompletionListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Refund\ProcessRefundPaymentOnConfirmListener;
use Sylius\AdyenPlugin\EventListener\Workflow\Refund\ResolveOrderFullyRefundedListener;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('sylius_adyen.listener.workflow.refund.process_on_confirm', ProcessRefundPaymentOnConfirmListener::class)
        ->args([service('sylius_adyen.processor.refund.refund_payment_state')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_refund_refund_payment.completed.confirm', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.refund.resolve_order_fully_refunded', ResolveOrderFullyRefundedListener::class)
        ->args([service('sylius_refund.state_resolver.order_fully_refunded')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_refund_refund_payment.completed.complete', 'priority' => 100])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_refund_refund_payment.completed.confirm', 'priority' => 100]);

    $services->set('sylius_adyen.listener.workflow.refund.refund_payment.guard_complete', GuardRefundPaymentCompletionListener::class)
        ->args([service('sylius_adyen.checker.refund_payment.completion_eligibility')])
        ->tag('kernel.event_listener', ['event' => 'workflow.sylius_refund_refund_payment.guard.complete', 'priority' => 100]);
};
