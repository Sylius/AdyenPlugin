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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\CreateReferenceForPayment;
use Sylius\AdyenPlugin\Bus\Command\PaymentStatusReceived;
use Sylius\AdyenPlugin\Bus\Handler\PaymentStatusReceivedHandler;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\Bundle\ApiBundle\Command\Checkout\SendOrderConfirmation;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class PaymentStatusReceivedHandlerTest extends TestCase
{
    private MockObject|StateMachineInterface $stateMachine;

    private MockObject|RepositoryInterface $paymentRepository;

    private MockObject|RepositoryInterface $orderRepository;

    private MessageBusInterface|MockObject $commandBus;

    private MockObject|PaymentCommandFactoryInterface $commandFactory;

    private PaymentStatusReceivedHandler $handler;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->paymentRepository = $this->createMock(RepositoryInterface::class);
        $this->orderRepository = $this->createMock(RepositoryInterface::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->commandFactory = $this->createMock(PaymentCommandFactoryInterface::class);

        $this->handler = new PaymentStatusReceivedHandler(
            $this->stateMachine,
            $this->paymentRepository,
            $this->orderRepository,
            $this->commandBus,
            $this->commandFactory,
        );
    }

    #[DataProvider('provideForTestFlow')]
    public function testFlow(string $resultCode, bool $shouldPass): void
    {
        $payment = $this->createPaymentWithOrder($resultCode);

        $invocation = $shouldPass ? $this->once() : $this->never();
        $this->stateMachine
            ->expects($invocation)
            ->method('can')
        ;

        $this->commandBus
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]))
        ;

        $command = new PaymentStatusReceived($payment);
        ($this->handler)($command);
    }

    public function testCreateReferenceForPaymentIsAlwaysDispatched(): void
    {
        $payment = $this->createPaymentWithOrder('refused');

        $createReferenceDispatched = false;
        $this->commandBus
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use (&$createReferenceDispatched) {
                if ($command instanceof CreateReferenceForPayment) {
                    $createReferenceDispatched = true;
                }

                return Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]);
            })
        ;

        $this->paymentRepository
            ->expects($this->once())
            ->method('add')
            ->with($payment)
        ;

        $command = new PaymentStatusReceived($payment);
        ($this->handler)($command);

        $this->assertTrue($createReferenceDispatched, 'CreateReferenceForPayment command should be dispatched');
    }

    #[DataProvider('provideOrderStateTransitionScenarios')]
    public function testOrderStateTransition(
        string $resultCode,
        ?string $token,
        bool $canTransition,
        bool $shouldApplyTransition,
        bool $shouldAddOrder,
        bool $shouldDispatchConfirmation,
        ?string $expectedToken = null,
    ): void {
        $payment = $this->createPaymentWithOrder($resultCode, $token);
        $order = $payment->getOrder();

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE)
            ->willReturn($canTransition)
        ;

        $this->stateMachine
            ->expects($shouldApplyTransition ? $this->once() : $this->never())
            ->method('apply')
            ->with($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE)
        ;

        $this->orderRepository
            ->expects($shouldAddOrder ? $this->once() : $this->never())
            ->method('add')
            ->with($order)
        ;

        $this->paymentRepository
            ->expects($this->once())
            ->method('add')
            ->with($payment)
        ;

        $sendOrderConfirmationDispatched = false;
        $this->commandBus
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($command) use (&$sendOrderConfirmationDispatched, $expectedToken) {
                if ($command instanceof SendOrderConfirmation) {
                    $sendOrderConfirmationDispatched = true;
                    if ($expectedToken !== null) {
                        $this->assertSame($expectedToken, $command->orderToken);
                    }
                }

                return Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]);
            })
        ;

        $command = new PaymentStatusReceived($payment);
        ($this->handler)($command);

        $this->assertSame($shouldDispatchConfirmation, $sendOrderConfirmationDispatched);
    }

    public function testUnmappedAdyenActionExceptionHandled(): void
    {
        $payment = $this->createPaymentWithOrder('refused');

        $this->commandFactory
            ->method('createForEvent')
            ->willThrowException(new UnmappedAdyenActionException('Unknown action'))
        ;

        $this->commandBus
            ->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturn(Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]))
        ;

        $this->paymentRepository
            ->expects($this->once())
            ->method('add')
            ->with($payment)
        ;

        $command = new PaymentStatusReceived($payment);
        ($this->handler)($command);
    }

    public function testInvalidArgumentExceptionHandledDuringReferenceCreation(): void
    {
        $payment = $this->createPaymentWithOrder('redirectshopper');

        $this->commandBus
            ->method('dispatch')
            ->willReturnCallback(function ($command) {
                if ($command instanceof CreateReferenceForPayment) {
                    throw new \InvalidArgumentException('No PSP reference available');
                }

                return Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]);
            })
        ;

        $this->paymentRepository
            ->expects($this->never())
            ->method('add')
        ;

        $command = new PaymentStatusReceived($payment);
        ($this->handler)($command);
    }

    public static function provideForTestFlow(): array
    {
        $result = [
            'dummy result code' => [
                'dummy', false,
            ],
            'refused result code' => [
                'refused', false,
            ],
            'cancelled result code' => [
                'cancelled', false,
            ],
            'error result code' => [
                'error', false,
            ],
        ];

        foreach (PaymentStatusReceivedHandler::ALLOWED_EVENT_NAMES as $eventName) {
            $result[sprintf('valid result code: %s', $eventName)] = [
                $eventName, true,
            ];
            $result[sprintf('uppercase valid result code: %s', strtoupper($eventName))] = [
                strtoupper($eventName), true,
            ];
        }

        return $result;
    }

    public static function provideOrderStateTransitionScenarios(): array
    {
        return [
            'accepted result code with token - should complete order and send confirmation' => [
                'authorised', 'test-token', true, true, true, true, 'test-token',
            ],
            'accepted result code without token - should complete order but not send confirmation' => [
                'authorised', null, true, true, true, false,
            ],
            'accepted result code but cannot complete - should not complete order' => [
                'authorised', null, false, false, false, false,
            ],
            'uppercase accepted result code - should normalize and complete' => [
                'AUTHORISED', null, true, true, true, false,
            ],
        ];
    }

    private function createPaymentWithOrder(string $resultCode, ?string $token = null): Payment
    {
        $order = new Order();
        if ($token !== null) {
            $order->setTokenValue($token);
        }

        $payment = new Payment();
        $payment->setDetails([
            'resultCode' => $resultCode,
            'pspReference' => '123',
        ]);
        $order->addPayment($payment);

        return $payment;
    }
}
