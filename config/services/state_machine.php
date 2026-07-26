<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\StateMachine\Guard\AdyenPaymentGuard;
use Sylius\AdyenPlugin\StateMachine\Guard\OrderGuard;
use Sylius\AdyenPlugin\StateMachine\Guard\OrderPaymentGuard;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.state_machine.guard.payment', AdyenPaymentGuard::class)
        ->args([service('sylius_adyen.checker.adyen_payment_method')]);

    $services->set('sylius_adyen.state_machine.guard.order_payment', OrderPaymentGuard::class)
        ->args([service('sylius_adyen.checker.adyen_payment_method')]);

    $services->set('sylius_adyen.state_machine.guard.order', OrderGuard::class)
        ->args([service('sylius_adyen.checker.adyen_payment_method')]);
};
