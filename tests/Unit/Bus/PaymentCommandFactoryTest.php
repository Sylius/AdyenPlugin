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

namespace Tests\Sylius\AdyenPlugin\Unit\Bus;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\PaymentCancelledCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentRefunded;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactory;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;

class PaymentCommandFactoryTest extends TestCase
{
    /** @var EventCodeResolverInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $eventCodeResolver;

    /** @var PaymentCommandFactory */
    private $factory;

    protected function setUp(): void
    {
        $this->eventCodeResolver = $this->createMock(EventCodeResolverInterface::class);
        $this->factory = new PaymentCommandFactory($this->eventCodeResolver);
    }

    public function testCreateForEventWithNotificationDataUsesEventCodeResolver(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = 'CANCEL_OR_REFUND';

        $this->eventCodeResolver->expects($this->once())
            ->method('resolve')
            ->with($notificationData)
            ->willReturn('cancellation');

        $command = $this->factory->createForEvent('ignored_event', $payment, $notificationData);

        $this->assertInstanceOf(PaymentCancelledCommand::class, $command);
        $this->assertSame($payment, $command->getPayment());
    }

    public function testCreateForEventCreatesPaymentRefundedCommandWithNotificationData(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = 'CANCEL_OR_REFUND';

        $this->eventCodeResolver->expects($this->once())
            ->method('resolve')
            ->with($notificationData)
            ->willReturn('refund');

        $command = $this->factory->createForEvent('ignored_event', $payment, $notificationData);

        $this->assertInstanceOf(PaymentRefunded::class, $command);
        $this->assertSame($payment, $command->payment);
        $this->assertSame($notificationData, $command->notificationData);
    }

    public function testCreateForEventThrowsExceptionForUnmappedEvent(): void
    {
        $this->expectException(UnmappedAdyenActionException::class);
        $this->expectExceptionMessage('Event "unknown_event" has no handler registered');

        $payment = $this->createMock(PaymentInterface::class);

        $this->factory->createForEvent('unknown_event', $payment);
    }

    public function testCreateForEventReturnsObjectForPaymentRefundedCommand(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $notificationData = new NotificationItemData();

        $this->eventCodeResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('refund');

        $command = $this->factory->createForEvent('refund', $payment, $notificationData);

        // PaymentRefundedCommand doesn't implement PaymentLifecycleCommand, but should still be returned as object
        $this->assertInstanceOf(PaymentRefunded::class, $command);
        $this->assertIsObject($command);
    }
}
