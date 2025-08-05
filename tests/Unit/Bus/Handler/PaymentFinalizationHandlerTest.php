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
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\CapturePayment;
use Sylius\AdyenPlugin\Bus\Command\PaymentFinalizationCommand;
use Sylius\AdyenPlugin\Bus\Handler\PaymentFinalizationHandler;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\OrderPaymentStates;

class PaymentFinalizationHandlerTest extends TestCase
{
    use StateMachineTrait;

    /** @var PaymentFinalizationHandler */
    private $handler;

    /** @var mixed|\PHPUnit\Framework\MockObject\MockObject|EntityRepository */
    private $orderRepository;

    protected function setUp(): void
    {
        $this->setupStateMachineMocks();

        $this->orderRepository = $this->createMock(EntityRepository::class);

        $this->handler = new PaymentFinalizationHandler(
            $this->stateMachineFactory,
            $this->orderRepository,
        );
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
            'capture action' => [
                CapturePayment::class,
            ],
            'authorize action' => [
                AuthorizePayment::class,
            ],
        ];
    }

    #[DataProvider('provideForTestForApplicable')]
    public function testApplicable(string $class): void
    {
        $order = new Order();
        $order->setPaymentState(OrderPaymentStates::STATE_AUTHORIZED);

        $payment = new Payment();
        $payment->setOrder($order);

        /**
         * @var PaymentFinalizationCommand $command
         */
        $command = new $class($payment);

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($this->equalTo($command->getPaymentTransition()))
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
}
