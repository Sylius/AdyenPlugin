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
use Sylius\AdyenPlugin\Bus\Command\PaymentStatusReceived;
use Sylius\AdyenPlugin\Bus\Handler\PaymentStatusReceivedHandler;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class PaymentStatusReceivedHandlerTest extends TestCase
{
    private const TESTING_RESULT_CODE = 'Chrząszcz';

    use StateMachineTrait;

    /** @var PaymentStatusReceivedHandler */
    private $handler;

    /** @var mixed|\PHPUnit\Framework\MockObject\MockObject|EntityRepository */
    private $paymentRepository;

    /** @var \PHPUnit\Framework\MockObject\MockObject|EntityRepository */
    private $orderRepository;

    /** @var mixed|\Symfony\Component\Messenger\MessageBusInterface */
    private $commandBus;

    /** @var PaymentCommandFactoryInterface|mixed|\PHPUnit\Framework\MockObject\MockObject */
    private $commandFactory;

    protected function setUp(): void
    {
        $this->setupStateMachineMocks();

        $this->paymentRepository = $this->createMock(EntityRepository::class);
        $this->orderRepository = $this->createMock(EntityRepository::class);
        $this->commandBus = $this->createMock(MessageBusInterface::class);
        $this->commandFactory = $this->createMock(PaymentCommandFactoryInterface::class);
        $this->handler = new PaymentStatusReceivedHandler(
            $this->stateMachineFactory,
            $this->paymentRepository,
            $this->orderRepository,
            $this->commandBus,
            $this->commandFactory,
        );
    }

    public static function provideForTestFlow(): array
    {
        $result = [
            'dummy result code' => [
                self::TESTING_RESULT_CODE, false,
            ],
        ];

        foreach (PaymentStatusReceivedHandler::ALLOWED_EVENT_NAMES as $eventName) {
            $result[sprintf('valid result code: %s', $eventName)] = [
                $eventName, true,
            ];
        }

        return $result;
    }

    #[DataProvider('provideForTestFlow')]
    public function testFlow(string $resultCode, bool $shouldPass): void
    {
        $order = new Order();

        $payment = new Payment();
        $payment->setDetails([
            'resultCode' => $resultCode,
            'pspReference' => '123',
        ]);
        $order->addPayment($payment);

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
}
