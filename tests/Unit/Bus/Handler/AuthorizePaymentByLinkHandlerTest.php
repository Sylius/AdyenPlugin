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

namespace Tests\Sylius\AdyenPlugin\Unit\Bus\Handler;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePaymentByLink;
use Sylius\AdyenPlugin\Bus\Command\CreateReferenceForPayment;
use Sylius\AdyenPlugin\Bus\Handler\AuthorizePaymentByLinkHandler;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\Payment;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class AuthorizePaymentByLinkHandlerTest extends TestCase
{
    private const PAYMENT_LINK_ID = 'test-payment-link-id';

    private MockObject|PaymentLinkRepositoryInterface $paymentLinkRepository;

    private MockObject|NormalizerInterface $normalizer;

    private EntityManagerInterface|MockObject $entityManager;

    private MessageBusInterface|MockObject $commandBus;

    private AuthorizePaymentByLinkHandler $handler;

    protected function setUp(): void
    {
        $this->paymentLinkRepository = $this->createMock(PaymentLinkRepositoryInterface::class);
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new AuthorizePaymentByLinkHandler(
            $this->paymentLinkRepository,
            $this->normalizer,
            $this->entityManager,
            $this->commandBus,
        );
    }

    public function testInvoke(): void
    {
        $payment = new Payment();
        $notificationItemData = new NotificationItemData();
        $notificationItemData->additionalData = ['paymentLinkId' => self::PAYMENT_LINK_ID];
        $notificationItemData->pspReference = 'test-psp-reference';
        $notificationItemData->eventCode = 'AUTHORISATION';

        $normalizedData = [
            'pspReference' => 'test-psp-reference',
            'eventCode' => 'AUTHORISATION',
            'additionalData' => ['paymentLinkId' => self::PAYMENT_LINK_ID],
        ];

        $command = new AuthorizePaymentByLink($payment, $notificationItemData);

        $this->normalizer
            ->expects($this->once())
            ->method('normalize')
            ->with($notificationItemData, 'array')
            ->willReturn($normalizedData)
        ;

        $dispatchedCommands = [];
        $this->commandBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($command) use (&$dispatchedCommands) {
                $dispatchedCommands[] = $command;

                return Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]);
            })
        ;

        $this->paymentLinkRepository
            ->expects($this->once())
            ->method('removeByLinkId')
            ->with(self::PAYMENT_LINK_ID)
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        ($this->handler)($command);

        self::assertEquals($normalizedData, $payment->getDetails());
        self::assertInstanceOf(CreateReferenceForPayment::class, $dispatchedCommands[0]);
        self::assertInstanceOf(AuthorizePayment::class, $dispatchedCommands[1]);
        self::assertSame($payment, $dispatchedCommands[0]->getPayment());
        self::assertSame($payment, $dispatchedCommands[1]->getPayment());
    }

    public function testInvokeWithDifferentPaymentLinkId(): void
    {
        $payment = new Payment();
        $notificationItemData = new NotificationItemData();
        $notificationItemData->additionalData = ['paymentLinkId' => 'another-link-id'];
        $notificationItemData->pspReference = 'test-psp-reference-2';

        $normalizedData = [
            'pspReference' => 'test-psp-reference-2',
            'additionalData' => ['paymentLinkId' => 'another-link-id'],
        ];

        $command = new AuthorizePaymentByLink($payment, $notificationItemData);

        $this->normalizer
            ->expects($this->once())
            ->method('normalize')
            ->with($notificationItemData, 'array')
            ->willReturn($normalizedData)
        ;

        $this->commandBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]))
        ;

        $this->paymentLinkRepository
            ->expects($this->once())
            ->method('removeByLinkId')
            ->with('another-link-id')
        ;

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
        ;

        ($this->handler)($command);

        self::assertEquals($normalizedData, $payment->getDetails());
    }
}
