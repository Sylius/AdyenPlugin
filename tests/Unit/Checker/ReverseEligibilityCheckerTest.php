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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Checker\ReverseEligibilityChecker;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class ReverseEligibilityCheckerTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private MockObject|StateMachineInterface $stateMachine;

    private ReverseEligibilityChecker $checker;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);

        $this->checker = new ReverseEligibilityChecker(
            $this->adyenPaymentMethodChecker,
            $this->stateMachine,
        );
    }

    public function testReturnsFalseWhenOrderIsNotFulfilled(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_NEW)
        ;

        $this->assertFalse($this->checker->canReverse($order));
    }

    public function testReturnsFalseWhenPaymentIsNull(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order
            ->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED)
        ;
        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn(null)
        ;

        $this->assertFalse($this->checker->canReverse($order));
    }

    public function testReturnsFalseWhenPaymentIsNotAdyen(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED)
        ;
        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment)
        ;

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false)
        ;

        $this->assertFalse($this->checker->canReverse($order));
    }

    public function testReturnsFalseWhenCaptureModeIsNotAutomatic(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED)
        ;
        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment)
        ;

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true)
        ;

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false)
        ;

        $this->assertFalse($this->checker->canReverse($order));
    }

    public function testReturnsFalseWhenStateMachineCannotTransition(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED)
        ;
        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment)
        ;

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true)
        ;

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true)
        ;

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REVERSE)
            ->willReturn(false)
        ;

        $this->assertFalse($this->checker->canReverse($order));
    }

    public function testReturnsTrueWhenAllConditionsAreMet(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $order = $this->createMock(OrderInterface::class);

        $order
            ->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED)
        ;
        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment)
        ;

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true)
        ;

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true)
        ;

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REVERSE)
            ->willReturn(true)
        ;

        $this->assertTrue($this->checker->canReverse($order));
    }
}
