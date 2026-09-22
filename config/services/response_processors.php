<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\AbstractProcessor;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\FailedResponseProcessor;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\FallbackResponseProcessor;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\PaymentProcessingResponseProcessor;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\SuccessfulResponseProcessor;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.processor.payment_response', PaymentResponseProcessor::class)
        ->args([
            tagged_iterator('sylius_adyen.processor.payment_response'),
            service('router'),
        ]);

    $services->set('sylius_adyen.processor.payment_response_processor.abstract', AbstractProcessor::class)
        ->abstract()
        ->args([
            service('router'),
            service('translator'),
        ]);

    $services->set('sylius_adyen.processor.payment_response_processor.failed_response', FailedResponseProcessor::class)
        ->parent('sylius_adyen.processor.payment_response_processor.abstract')
        ->args([
            service('sylius.command_bus'),
            service('sylius_adyen.bus.payment_command_factory'),
        ])
        ->tag('sylius_adyen.processor.payment_response');

    $services->set('sylius_adyen.processor.payment_response_processor.successful_response', SuccessfulResponseProcessor::class)
        ->parent('sylius_adyen.processor.payment_response_processor.abstract')
        ->args([
            service('sylius.command_bus'),
            service('sylius_adyen.bus.payment_command_factory'),
        ])
        ->tag('sylius_adyen.processor.payment_response');

    $services->set('sylius_adyen.processor.payment_response_processor.payment_processing_response', PaymentProcessingResponseProcessor::class)
        ->parent('sylius_adyen.processor.payment_response_processor.abstract')
        ->args([
            service('sylius.command_bus'),
            service('sylius_adyen.bus.payment_command_factory'),
        ])
        ->tag('sylius_adyen.processor.payment_response');

    $services->set('sylius_adyen.processor.payment_response_processor.fallback_response', FallbackResponseProcessor::class)
        ->parent('sylius_adyen.processor.payment_response_processor.abstract')
        ->tag('sylius_adyen.processor.payment_response', ['priority' => -100]);
};
