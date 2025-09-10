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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePaymentByLink;
use Sylius\AdyenPlugin\Bus\Command\CapturePayment;
use Sylius\AdyenPlugin\Bus\Command\MarkPaymentAsProcessedCommand;
use Sylius\AdyenPlugin\Bus\Command\NotificationDataAwarePaymentCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentCancelledCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentFailedCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentRefunded;
use Sylius\AdyenPlugin\Bus\Command\PaymentStatusReceived;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactory;
use Sylius\AdyenPlugin\Client\ResponseStatus;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentCommandFactoryTest extends TestCase
{
    private EventCodeResolverInterface|MockObject $eventCodeResolver;

    private PaymentCommandFactory $factory;

    protected function setUp(): void
    {
        $this->eventCodeResolver = $this->createMock(EventCodeResolverInterface::class);
        $this->factory = new PaymentCommandFactory($this->eventCodeResolver);
    }

    #[DataProvider('paymentLifecycleCommandMappingProvider')]
    public function testCreateForEventCreatesPaymentLifecycleCommands(string $event, string $expectedClass): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $command = $this->factory->createForEvent($event, $payment);

        self::assertInstanceOf($expectedClass, $command);
        self::assertInstanceOf(PaymentLifecycleCommand::class, $command);
        self::assertSame($payment, $command->getPayment());
    }

    public static function paymentLifecycleCommandMappingProvider(): iterable
    {
        yield 'authorisation event creates AuthorizePayment' => [
            'event' => EventCodeResolverInterface::EVENT_AUTHORIZATION,
            'expectedClass' => AuthorizePayment::class,
        ];
        yield 'authorised event creates AuthorizePayment' => [
            'event' => ResponseStatus::AUTHORISED,
            'expectedClass' => AuthorizePayment::class,
        ];
        yield 'payment_status_received event creates PaymentStatusReceived' => [
            'event' => ResponseStatus::PAYMENT_STATUS_RECEIVED,
            'expectedClass' => PaymentStatusReceived::class,
        ];
        yield 'capture event creates CapturePayment' => [
            'event' => EventCodeResolverInterface::EVENT_CAPTURE,
            'expectedClass' => CapturePayment::class,
        ];
        yield 'received event creates MarkPaymentAsProcessedCommand' => [
            'event' => ResponseStatus::RECEIVED,
            'expectedClass' => MarkPaymentAsProcessedCommand::class,
        ];
        yield 'refused event creates PaymentFailedCommand' => [
            'event' => ResponseStatus::REFUSED,
            'expectedClass' => PaymentFailedCommand::class,
        ];
        yield 'rejected event creates PaymentFailedCommand' => [
            'event' => ResponseStatus::REJECTED,
            'expectedClass' => PaymentFailedCommand::class,
        ];
        yield 'cancellation event creates PaymentCancelledCommand' => [
            'event' => EventCodeResolverInterface::EVENT_CANCELLATION,
            'expectedClass' => PaymentCancelledCommand::class,
        ];
    }

    #[DataProvider('notificationDataAwareCommandProvider')]
    public function testCreateForEventCreatesNotificationDataAwareCommands(
        string $event,
        string $resolvedEvent,
        string $expectedClass,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = $event;
        $notificationData->pspReference = 'TEST-PSP-REF-123';
        $notificationData->merchantReference = 'MERCHANT-REF-456';

        $this->eventCodeResolver->expects($this->once())
            ->method('resolve')
            ->with($notificationData)
            ->willReturn($resolvedEvent);

        $command = $this->factory->createForEvent('ignored_event', $payment, $notificationData);

        self::assertInstanceOf($expectedClass, $command);
        self::assertInstanceOf(NotificationDataAwarePaymentCommand::class, $command);
        self::assertSame($payment, $command->payment);
        self::assertSame($notificationData, $command->notificationData);

        self::assertEquals('TEST-PSP-REF-123', $command->notificationData->pspReference);
        self::assertEquals('MERCHANT-REF-456', $command->notificationData->merchantReference);
        self::assertEquals($event, $command->notificationData->eventCode);
    }

    public static function notificationDataAwareCommandProvider(): iterable
    {
        yield 'PaymentRefunded with notification data' => [
            'event' => 'CANCEL_OR_REFUND',
            'resolvedEvent' => 'refund',
            'expectedClass' => PaymentRefunded::class,
        ];
        yield 'AuthorizePaymentByLink with notification data' => [
            'event' => 'AUTHORISATION',
            'resolvedEvent' => 'pay_by_link_authorisation',
            'expectedClass' => AuthorizePaymentByLink::class,
        ];
    }

    public function testCreateForEventWithCustomMapping(): void
    {
        $customMapping = ['custom_event' => AuthorizePayment::class];
        $factory = new PaymentCommandFactory($this->eventCodeResolver, $customMapping);
        $payment = $this->createMock(PaymentInterface::class);

        $command = $factory->createForEvent('custom_event', $payment);

        self::assertInstanceOf(AuthorizePayment::class, $command);
        self::assertSame($payment, $command->getPayment());
    }

    public function testCreateForEventThrowsExceptionForUnmappedEvent(): void
    {
        $this->expectException(UnmappedAdyenActionException::class);
        $this->expectExceptionMessage('Event "unknown_event" has no handler registered');

        $payment = $this->createMock(PaymentInterface::class);

        $this->factory->createForEvent('unknown_event', $payment);
    }

    public function testCreateForEventMergesDefaultAndCustomMappings(): void
    {
        $customMapping = ['custom_event' => AuthorizePayment::class];
        $factory = new PaymentCommandFactory($this->eventCodeResolver, $customMapping);
        $payment = $this->createMock(PaymentInterface::class);

        // Test default mapping still works
        $defaultCommand = $factory->createForEvent('authorisation', $payment);
        self::assertInstanceOf(AuthorizePayment::class, $defaultCommand);

        // Test custom mapping works
        $customCommand = $factory->createForEvent('custom_event', $payment);
        self::assertInstanceOf(AuthorizePayment::class, $customCommand);
    }
}
