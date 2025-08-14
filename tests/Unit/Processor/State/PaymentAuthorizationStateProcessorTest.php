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

namespace Tests\Sylius\AdyenPlugin\Unit\Processor\State;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\PaymentTransitions;
use Sylius\AdyenPlugin\Processor\State\PaymentAuthorizationStateProcessor;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;

final class PaymentAuthorizationStateProcessorTest extends TestCase
{
    private MockObject|StateMachineInterface $stateMachine;

    private EntityManagerInterface|MockObject $entityManager;

    private PaymentAuthorizationStateProcessor $processor;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->processor = new PaymentAuthorizationStateProcessor(
            $this->stateMachine,
            $this->entityManager,
        );
    }

    public function testProcessWithNullPaymentMethod(): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn(null);

        $this->stateMachine->expects($this->never())
            ->method('can');

        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->processor->process($payment);
    }

    public function testProcessWithNullGatewayConfig(): void
    {
        $payment = $this->createMock(Payment::class);
        $paymentMethod = $this->createMock(PaymentMethod::class);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn(null);

        $this->stateMachine->expects($this->never())
            ->method('can');

        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->processor->process($payment);
    }

    public function testProcessWithNonAdyenGateway(): void
    {
        $payment = $this->createMock(Payment::class);
        $paymentMethod = $this->createMock(PaymentMethod::class);
        $gatewayConfig = $this->createMock(GatewayConfig::class);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getFactoryName')
            ->willReturn('paypal');

        $this->stateMachine->expects($this->never())
            ->method('can');

        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->processor->process($payment);
    }

    public function testProcessWhenCannotTransitionToCapture(): void
    {
        $payment = $this->createMock(Payment::class);
        $paymentMethod = $this->createMock(PaymentMethod::class);
        $gatewayConfig = $this->createMock(GatewayConfig::class);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getFactoryName')
            ->willReturn(AdyenClientProviderInterface::FACTORY_NAME);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with(
                $payment,
                PaymentTransitions::GRAPH,
                PaymentTransitions::TRANSITION_CAPTURE,
            )
            ->willReturn(false);

        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->processor->process($payment);
    }

    public function testProcessSuccessfullyAppliesAuthorizeTransition(): void
    {
        $payment = $this->createMock(Payment::class);
        $paymentMethod = $this->createMock(PaymentMethod::class);
        $gatewayConfig = $this->createMock(GatewayConfig::class);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getFactoryName')
            ->willReturn(AdyenClientProviderInterface::FACTORY_NAME);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with(
                $payment,
                PaymentTransitions::GRAPH,
                PaymentTransitions::TRANSITION_CAPTURE,
            )
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with(
                $payment,
                PaymentTransitions::GRAPH,
                PaymentTransitions::TRANSITION_CAPTURE,
            );

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->processor->process($payment);
    }
}
