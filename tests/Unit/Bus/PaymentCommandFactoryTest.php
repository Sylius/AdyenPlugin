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

    public function testCreateForEventWithoutNotificationData(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $command = $this->factory->createForEvent('authorisation', $payment);

        $this->assertInstanceOf(AuthorizePayment::class, $command);
        $this->assertSame($payment, $command->getPayment());
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
        $this->assertSame($payment, $command->getPayment());
        $this->assertSame($notificationData, $command->getNotificationData());
    }

    public function testCreateForEventThrowsExceptionForUnmappedEvent(): void
    {
        $this->expectException(UnmappedAdyenActionException::class);
        $this->expectExceptionMessage('Event "unknown_event" has no handler registered');

        $payment = $this->createMock(PaymentInterface::class);

        $this->factory->createForEvent('unknown_event', $payment);
    }

    public function testCreateForEventWithCustomMapping(): void
    {
        $customMapping = ['custom_event' => AuthorizePayment::class];
        $factory = new PaymentCommandFactory($this->eventCodeResolver, $customMapping);
        $payment = $this->createMock(PaymentInterface::class);

        $command = $factory->createForEvent('custom_event', $payment);

        $this->assertInstanceOf(AuthorizePayment::class, $command);
        $this->assertSame($payment, $command->getPayment());
    }

    public function testCreateForEventMergesDefaultAndCustomMappings(): void
    {
        $customMapping = ['custom_event' => AuthorizePayment::class];
        $factory = new PaymentCommandFactory($this->eventCodeResolver, $customMapping);
        $payment = $this->createMock(PaymentInterface::class);

        // Test default mapping still works
        $defaultCommand = $factory->createForEvent('authorisation', $payment);
        $this->assertInstanceOf(AuthorizePayment::class, $defaultCommand);

        // Test custom mapping works
        $customCommand = $factory->createForEvent('custom_event', $payment);
        $this->assertInstanceOf(AuthorizePayment::class, $customCommand);
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
