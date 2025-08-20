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
use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\CapturePayment;
use Sylius\AdyenPlugin\Bus\Command\PaymentFinalizationCommand;
use Sylius\AdyenPlugin\Bus\Handler\PaymentFinalizationHandler;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class PaymentFinalizationHandlerTest extends TestCase
{
    private MockObject|RepositoryInterface $orderRepository;

    private MockObject|StateMachineInterface $stateMachine;

    private PaymentFinalizationHandler $handler;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(RepositoryInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);

        $this->handler = new PaymentFinalizationHandler(
            $this->stateMachine,
            $this->orderRepository,
        );
    }

    #[DataProvider('provideForTestForApplicable')]
    public function testApplicable(string $command, bool $canTransition): void
    {
        $order = new Order();
        $order->setPaymentState(OrderPaymentStates::STATE_AUTHORIZED);

        $payment = new Payment();
        $payment->setOrder($order);

        /**
         * @var PaymentFinalizationCommand $command
         */
        $command = new $command($payment);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentGraph::GRAPH, $this->equalTo($command->getPaymentTransition()))
            ->willReturn($canTransition)
        ;
        $this->stateMachine
            ->expects($canTransition ? $this->once() : $this->never())
            ->method('apply')
            ->with($payment, PaymentGraph::GRAPH, $this->equalTo($command->getPaymentTransition()))
        ;

        $this
            ->orderRepository
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->equalTo($order),
            )
        ;

        ($this->handler)($command);
    }

    public function testUnacceptable(): void
    {
        $order = new Order();
        $order->setPaymentState(OrderPaymentStates::STATE_PAID);

        $payment = new Payment();
        $payment->setOrder($order);

        $this->orderRepository
            ->expects($this->never())
            ->method('add')
        ;

        $command = new AuthorizePayment($payment);
        ($this->handler)($command);
    }

    public static function provideForTestForApplicable(): array
    {
        return [
            'capture action with transition' => [
                'command' => CapturePayment::class,
                'canTransition' => true,
            ],
            'authorize action with transition' => [
                'command' => AuthorizePayment::class,
                'canTransition' => true,
            ],
            'capture action without transition' => [
                'command' => CapturePayment::class,
                'canTransition' => false,
            ],
            'authorize action without transition' => [
                'command' => AuthorizePayment::class,
                'canTransition' => false,
            ],
        ];
    }
}
