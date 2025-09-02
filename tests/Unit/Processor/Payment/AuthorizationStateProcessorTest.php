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

namespace Tests\Sylius\AdyenPlugin\Unit\Processor\Payment;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Processor\Payment\AuthorizationStateProcessor;
use Sylius\Component\Core\Model\PaymentInterface;

final class AuthorizationStateProcessorTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private MockObject|StateMachineInterface $stateMachine;

    private EntityManagerInterface|MockObject $entityManager;

    private AuthorizationStateProcessor $processor;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->processor = new AuthorizationStateProcessor(
            $this->adyenPaymentMethodChecker,
            $this->stateMachine,
            $this->entityManager,
        );
    }

    public function testProcessSkipsNonAdyenPayments(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->willReturn(false);

        $this->adyenPaymentMethodChecker->expects($this->never())
            ->method('isCaptureMode');

        $this->processor->process($payment);
    }

    public function testProcessSkipsManualCaptureMode(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isCaptureMode')
            ->with($payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false);

        $this->stateMachine->expects($this->never())
            ->method('can');

        $this->processor->process($payment);
    }

    public function testProcessSkipsWhenCannotTransition(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker->method('isAdyenPayment')->willReturn(true);
        $this->adyenPaymentMethodChecker->method('isCaptureMode')->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->willReturn(false);

        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->processor->process($payment);
    }

    public function testProcessAppliesToCaptureWhenConditionsAreMet(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker->method('isAdyenPayment')->willReturn(true);
        $this->adyenPaymentMethodChecker->method('isCaptureMode')->willReturn(true);
        $this->stateMachine->method('can')->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CAPTURE);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->processor->process($payment);
    }
}
