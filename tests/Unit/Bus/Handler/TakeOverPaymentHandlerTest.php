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
use Sylius\AdyenPlugin\Bus\Command\TakeOverPayment;
use Sylius\AdyenPlugin\Bus\Handler\TakeOverPaymentHandler;
use Sylius\AdyenPlugin\Clearer\PaymentReferencesClearerInterface;
use Sylius\AdyenPlugin\Exception\AdyenPaymentMethodNotFoundException;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class TakeOverPaymentHandlerTest extends TestCase
{
    private const TEST_PAYMENT_CODE = 'Bakłażan';

    private const NEW_TEST_PAYMENT_CODE = 'Szczebrzeszyn';

    private MockObject|PaymentMethodRepositoryInterface $paymentMethodRepository;

    private MockObject|PaymentReferencesClearerInterface $paymentReferencesClearer;

    private EntityManagerInterface|MockObject $paymentManager;

    private TakeOverPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->paymentReferencesClearer = $this->createMock(PaymentReferencesClearerInterface::class);
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new TakeOverPaymentHandler(
            $this->paymentMethodRepository,
            $this->paymentReferencesClearer,
            $this->paymentManager,
        );
    }

    public function testTheSamePaymentMethod(): void
    {
        $this->paymentMethodRepository
            ->expects($this->never())
            ->method('getOneAdyenForCode')
        ;

        $this->paymentReferencesClearer
            ->expects($this->never())
            ->method('clear')
        ;

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode(self::TEST_PAYMENT_CODE);

        $command = new TakeOverPayment(
            $this->createPayment($paymentMethod)->getOrder(),
            self::TEST_PAYMENT_CODE,
        );
        ($this->handler)($command);
    }

    public function testThrowsExceptionWhenPaymentMethodNotFound(): void
    {
        $this->expectException(AdyenPaymentMethodNotFoundException::class);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode(self::TEST_PAYMENT_CODE);

        $this->paymentMethodRepository
            ->expects($this->once())
            ->method('getOneAdyenForCode')
            ->with(self::NEW_TEST_PAYMENT_CODE)
            ->willReturn(null)
        ;

        $this->paymentReferencesClearer
            ->expects($this->never())
            ->method('clear')
        ;

        $this->paymentManager
            ->expects($this->never())
            ->method('persist')
        ;

        $this->paymentManager
            ->expects($this->never())
            ->method('flush')
        ;

        $payment = $this->createPayment($paymentMethod);
        $command = new TakeOverPayment($payment->getOrder(), self::NEW_TEST_PAYMENT_CODE);

        ($this->handler)($command);
    }

    public function testChange(): void
    {
        $this->paymentManager
            ->expects($this->once())
            ->method('persist')
            ->with(
                $this->isInstanceOf(PaymentInterface::class),
            )
        ;

        $this->paymentManager
            ->expects($this->once())
            ->method('flush')
        ;

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode(self::TEST_PAYMENT_CODE);

        $newPaymentMethod = new PaymentMethod();
        $newPaymentMethod->setCode(self::NEW_TEST_PAYMENT_CODE);

        $this->paymentMethodRepository
            ->expects($this->once())
            ->method('getOneAdyenForCode')
            ->with(
                $this->equalTo(self::NEW_TEST_PAYMENT_CODE),
            )
            ->willReturn($newPaymentMethod)
        ;

        $payment = $this->createPayment($paymentMethod);

        $this->paymentReferencesClearer
            ->expects($this->once())
            ->method('clear')
            ->with($payment)
        ;

        $command = new TakeOverPayment($payment->getOrder(), self::NEW_TEST_PAYMENT_CODE);

        ($this->handler)($command);

        $this->assertEquals($newPaymentMethod, $payment->getMethod());
    }

    private function createPayment(PaymentMethodInterface $paymentMethod): PaymentInterface
    {
        $order = new Order();
        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $payment->setState(PaymentInterface::STATE_NEW);

        $order->addPayment($payment);

        return $payment;
    }
}
