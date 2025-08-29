<?php

/*
 * This file is part of the Sylius Adyen Plugin package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tests\Sylius\AdyenPlugin\Unit\Resolver\Payment;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolver;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;

class EventCodeResolverTest extends TestCase
{
    private EventCodeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new EventCodeResolver();
    }

    public function testResolveReturnsCancellationForCancelOrRefundWithCancelAction(): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND;
        $notificationData->additionalData = ['modification.action' => 'cancel'];

        $result = $this->resolver->resolve($notificationData);

        $this->assertEquals('cancellation', $result);
    }

    public function testResolveReturnsRefundForCancelOrRefundWithRefundAction(): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND;
        $notificationData->additionalData = ['modification.action' => 'refund'];

        $result = $this->resolver->resolve($notificationData);

        $this->assertEquals('refund', $result);
    }

    public function testResolveReturnsOriginalEventCodeForCancelOrRefundWithoutModificationAction(): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND;
        $notificationData->additionalData = [];

        $result = $this->resolver->resolve($notificationData);

        $this->assertEquals(EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND, $result);
    }

    public function testResolveReturnsOriginalEventCodeForCancelOrRefundWithNullAdditionalData(): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND;
        $notificationData->additionalData = null;

        $result = $this->resolver->resolve($notificationData);

        $this->assertEquals(EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND, $result);
    }

    public function testResolveReturnsOriginalEventCodeForCancelOrRefundWithUnknownModificationAction(): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND;
        $notificationData->additionalData = ['modification.action' => 'unknown'];

        $result = $this->resolver->resolve($notificationData);

        $this->assertEquals(EventCodeResolverInterface::EVENT_CANCEL_OR_REFUND, $result);
    }

    public function testResolveReturnsOriginalEventCodeForNonAuthorizationAndNonCancelOrRefund(): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = 'CAPTURE';

        $result = $this->resolver->resolve($notificationData);

        $this->assertEquals('CAPTURE', $result);
    }

    public function testResolveReturnsAuthorizationForAuthorization(): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = EventCodeResolverInterface::EVENT_AUTHORIZATION;
        $notificationData->additionalData = [];

        $result = $this->resolver->resolve($notificationData);

        $this->assertEquals(EventCodeResolverInterface::EVENT_AUTHORIZATION, $result);
    }

    public function testResolveReturnsPayByLinkAuthorizationForAuthorizationWithPaymentLinkId(): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = EventCodeResolverInterface::EVENT_AUTHORIZATION;
        $notificationData->additionalData = ['paymentLinkId' => 'PL123456789'];

        $result = $this->resolver->resolve($notificationData);

        $this->assertEquals(EventCodeResolverInterface::EVENT_PAY_BY_LINK_AUTHORISATION, $result);
    }
}
