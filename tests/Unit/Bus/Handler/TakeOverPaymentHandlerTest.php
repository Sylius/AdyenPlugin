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
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\TakeOverPayment;
use Sylius\AdyenPlugin\Bus\Handler\TakeOverPaymentHandler;
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

    /** @var PaymentMethodRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $paymentMethodRepository;

    /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $paymentManager;

    /** @var TakeOverPaymentHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = $this->createMock(PaymentMethodRepositoryInterface::class);
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new TakeOverPaymentHandler(
            $this->paymentMethodRepository,
            $this->paymentManager,
        );
    }

    public function testTheSamePaymentMethod(): void
    {
        $this->paymentMethodRepository
            ->expects($this->never())
            ->method('getOneForAdyenAndCode')
        ;

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode(self::TEST_PAYMENT_CODE);

        $command = new TakeOverPayment(
            $this->createPayment($paymentMethod)->getOrder(),
            self::TEST_PAYMENT_CODE,
        );
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

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setCode(self::TEST_PAYMENT_CODE);

        $newPaymentMethod = new PaymentMethod();
        $newPaymentMethod->setCode(self::NEW_TEST_PAYMENT_CODE);

        $this->paymentMethodRepository
            ->expects($this->once())
            ->method('getOneForAdyenAndCode')
            ->with(
                $this->equalTo(self::NEW_TEST_PAYMENT_CODE),
            )
            ->willReturn($newPaymentMethod)
        ;

        $payment = $this->createPayment($paymentMethod);
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
