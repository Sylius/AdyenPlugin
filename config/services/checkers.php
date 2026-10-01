<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodChecker;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Checker\Eligibility\ZoneShippingMethodEligibilityChecker;
use Sylius\AdyenPlugin\Checker\EsdCardPaymentSupportChecker;
use Sylius\AdyenPlugin\Checker\EsdCardPaymentSupportCheckerInterface;
use Sylius\AdyenPlugin\Checker\OrderCheckoutCompleteIntegrityChecker;
use Sylius\AdyenPlugin\Checker\OrderCheckoutCompleteIntegrityCheckerInterface;
use Sylius\AdyenPlugin\Checker\PaymentPayByLinkAvailabilityChecker;
use Sylius\AdyenPlugin\Checker\Refund\OrderRefundingAvailabilityChecker;
use Sylius\AdyenPlugin\Checker\Refund\OrderRefundsListAvailabilityChecker;
use Sylius\AdyenPlugin\Checker\Refund\RefundPaymentCompletionEligibilityChecker;
use Sylius\AdyenPlugin\Checker\Refund\RefundPaymentCompletionEligibilityCheckerInterface;
use Sylius\AdyenPlugin\Checker\ReverseEligibilityChecker;
use Sylius\AdyenPlugin\Checker\ReverseEligibilityCheckerInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $parameters->set('sylius_adyen.allowed_payment_states.generate_payment_link', ['new', 'processing']);

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.checker.esd_card_payment_support', EsdCardPaymentSupportChecker::class)
        ->args(['%sylius_adyen.esd.supported_card_brands%']);

    $services->alias(EsdCardPaymentSupportCheckerInterface::class, 'sylius_adyen.checker.esd_card_payment_support');

    $services->set('sylius_adyen.checker.adyen_payment_method', AdyenPaymentMethodChecker::class)
        ->args([
            service('sylius_adyen.repository.adyen_payment_detail'),
            service('sylius_adyen.repository.payment_link'),
            '%sylius_adyen.payment_methods.only_manual_capture_types%',
        ]);

    $services->alias(AdyenPaymentMethodCheckerInterface::class, 'sylius_adyen.checker.adyen_payment_method');

    $services->set('sylius_adyen.checker.reverse_eligibility', ReverseEligibilityChecker::class)
        ->args([
            service('sylius_adyen.checker.adyen_payment_method'),
            service('sylius_abstraction.state_machine'),
        ]);

    $services->alias(ReverseEligibilityCheckerInterface::class, 'sylius_adyen.checker.reverse_eligibility');

    $services->set('sylius_adyen.checker.payment_pay_by_link_availability', PaymentPayByLinkAvailabilityChecker::class)
        ->args([
            service('sylius_adyen.repository.payment_link'),
            service('sylius_adyen.repository.adyen_reference'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.checker.refund.order_refunds_list_availability', OrderRefundsListAvailabilityChecker::class)
        ->decorate('sylius_refund.checker.order_refunds_list_availability')
        ->args([
            service('.inner'),
            service('sylius.repository.order'),
            service('sylius_adyen.checker.adyen_payment_method'),
            '%sylius_adyen.allowed_payment_states.generate_payment_link%',
        ]);

    $services->set('sylius_adyen.checker.refund.order_refunding_availability', OrderRefundingAvailabilityChecker::class)
        ->decorate('sylius_refund.checker.order_refunding_availability')
        ->args([
            service('.inner'),
            service('sylius.repository.order'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.checker.zone_shipping_method_eligibility', ZoneShippingMethodEligibilityChecker::class)
        ->args([service('sylius.matcher.zone')])
        ->tag('sylius.shipping_method_eligibility_checker');

    $services->set('sylius_adyen.checker.order_checkout_complete_integrity', OrderCheckoutCompleteIntegrityChecker::class)
        ->args([
            service('sylius.order_processing.order_processor'),
            service('sylius.manager.order'),
            service('validator'),
            service('translator'),
        ]);

    $services->alias(OrderCheckoutCompleteIntegrityCheckerInterface::class, 'sylius_adyen.checker.order_checkout_complete_integrity');

    $services->set('sylius_adyen.checker.refund_payment.completion_eligibility', RefundPaymentCompletionEligibilityChecker::class)
        ->args([service('sylius_adyen.checker.adyen_payment_method')]);

    $services->alias(RefundPaymentCompletionEligibilityCheckerInterface::class, 'sylius_adyen.checker.refund_payment.completion_eligibility');
};
