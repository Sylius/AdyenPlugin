<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AdyenPlugin\Resolver\Address\StreetAddressResolver;
use Sylius\AdyenPlugin\Resolver\ExpressCheckout\CheckoutResolver;
use Sylius\AdyenPlugin\Resolver\ExpressCheckout\CheckoutResolverInterface;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver\PaymentNotificationResolver;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationResolver\RefundNotificationResolver;
use Sylius\AdyenPlugin\Resolver\Notification\NotificationToCommandResolver;
use Sylius\AdyenPlugin\Resolver\Notification\Serializer\NotificationItemNormalizer;
use Sylius\AdyenPlugin\Resolver\Order\FailedOrderPaymentStateResolver;
use Sylius\AdyenPlugin\Resolver\Order\OrderFullyRefundedStateResolver;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolver;
use Sylius\AdyenPlugin\Resolver\Payment\PaymentDetailsResolver;
use Sylius\AdyenPlugin\Resolver\Product\ThumbnailUrlResolver;
use Sylius\AdyenPlugin\Resolver\ShopperReferenceResolver;
use Sylius\AdyenPlugin\Resolver\ShopperReferenceResolverInterface;
use Sylius\AdyenPlugin\Resolver\Version\VersionResolver;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->defaults()
        ->public();

    $services->set('sylius_adyen.resolver.shopper_reference', ShopperReferenceResolver::class)
        ->args([
            service('sylius_adyen.repository.shopper_reference'),
            service('sylius_adyen.factory.shopper_reference'),
        ]);

    $services->alias(ShopperReferenceResolverInterface::class, 'sylius_adyen.resolver.shopper_reference');

    $services->set('sylius_adyen.resolver.order.payment_checkout_order', PaymentCheckoutOrderResolver::class)
        ->args([
            service('request_stack'),
            service('sylius.context.cart'),
            service('sylius.repository.order'),
        ]);

    $services->set('sylius_adyen.resolver.notification.notification_resolver.payment_notification', PaymentNotificationResolver::class)
        ->args([
            service('sylius_adyen.repository.adyen_reference'),
            service('sylius_adyen.repository.payment_link'),
            service('sylius_adyen.bus.payment_command_factory'),
        ])
        ->tag('sylius_adyen.resolver.notification.command_resolver');

    $services->set('sylius_adyen.resolver.notification.processor.refund_notification', RefundNotificationResolver::class)
        ->args([service('sylius_adyen.repository.adyen_reference')])
        ->tag('sylius_adyen.resolver.notification.command_resolver');

    $services->set('sylius_adyen.resolver.notification.notification_command', NotificationToCommandResolver::class)
        ->args([tagged_iterator('sylius_adyen.resolver.notification.command_resolver')]);

    $services->set('sylius_adyen.resolver.version.version', VersionResolver::class)
        ->args(['%sylius_adyen.integrator_name%']);

    $services->set('sylius_adyen.resolver.notification.notification', NotificationResolver::class)
        ->args([
            service('serializer'),
            service('validator'),
            service('monolog.logger.adyen'),
        ]);

    $services->set('sylius_adyen.resolver.notification.serializer.notification_item_normalizer', NotificationItemNormalizer::class)
        ->tag('serializer.normalizer');

    $services->set('sylius_adyen.resolver.payment.payment_details', PaymentDetailsResolver::class)
        ->args([
            service('sylius.repository.order'),
            service('sylius_adyen.provider.adyen_client'),
            service('doctrine.orm.entity_manager'),
        ]);

    $services->set('sylius_adyen.resolver.product.thumbnail_url', ThumbnailUrlResolver::class)
        ->args([service('liip_imagine.cache.manager')]);

    $services->set('sylius_adyen.resolver.address.street_address', StreetAddressResolver::class);

    $services->set('sylius_adyen.resolver.order.express_checkout.checkout', CheckoutResolver::class)
        ->args([
            service('sylius.manager.order'),
            service('sylius_abstraction.state_machine'),
            service('sylius_adyen.repository.query.adyen_payment_method'),
            service('sylius_adyen.checker.order_checkout_complete_integrity'),
        ]);

    $services->alias(CheckoutResolverInterface::class, 'sylius_adyen.resolver.order.express_checkout.checkout');

    $services->set('sylius_adyen.state_resolver.order_failed_payment', FailedOrderPaymentStateResolver::class)
        ->args([
            service('sylius_abstraction.state_machine'),
            service('sylius_adyen.checker.adyen_payment_method'),
        ]);

    $services->set('sylius_adyen.refund.state_resolver.order_fully_refunded', OrderFullyRefundedStateResolver::class)
        ->args([service('sylius_refund.state_resolver.order_fully_refunded')]);
};
