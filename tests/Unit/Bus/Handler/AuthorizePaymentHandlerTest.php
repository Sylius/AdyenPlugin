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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Handler\AuthorizePaymentHandler;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Payment\PaymentTransitions;

final class AuthorizePaymentHandlerTest extends TestCase
{
    private MockObject|StateMachineInterface $stateMachine;
    private MockObject|AdyenReferenceRepositoryInterface $referenceRepository;

    private AuthorizePaymentHandler $handler;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->referenceRepository = $this->createMock(AdyenReferenceRepositoryInterface::class);

        $this->handler = new AuthorizePaymentHandler(
            $this->stateMachine,
            $this->referenceRepository,
        );
    }

    public function testReturnsEarlyWhenReferenceNotFound(): void
    {
        $command = new AuthorizePayment('adyen', 'PSP-123');

        $this->referenceRepository
            ->expects($this->once())
            ->method('getOneByPaymentMethodCodeAndReference')
            ->with('adyen', 'PSP-123')
            ->willReturn(null);

        $this->stateMachine
            ->expects($this->never())
            ->method('can');

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        ($this->handler)($command);
    }

    public function testAppliesAuthorizeTransitionWhenAllowed(): void
    {
        $command = new AuthorizePayment('adyen', 'PSP-456');

        $payment = new Payment();
        $reference = $this->createMock(AdyenReferenceInterface::class);
        $reference->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);

        $this->referenceRepository
            ->expects($this->once())
            ->method('getOneByPaymentMethodCodeAndReference')
            ->with('adyen', 'PSP-456')
            ->willReturn($reference);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_AUTHORIZE)
            ->willReturn(true);

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_AUTHORIZE);

        ($this->handler)($command);
    }

    public function testDoesNotApplyAuthorizeTransitionWhenNotAllowed(): void
    {
        $command = new AuthorizePayment('adyen', 'PSP-789');

        $payment = new Payment();
        $reference = $this->createMock(AdyenReferenceInterface::class);
        $reference->expects($this->once())
            ->method('getPayment')
            ->willReturn($payment);

        $this->referenceRepository
            ->expects($this->once())
            ->method('getOneByPaymentMethodCodeAndReference')
            ->with('adyen', 'PSP-789')
            ->willReturn($reference);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_AUTHORIZE)
            ->willReturn(false);

        $this->stateMachine
            ->expects($this->never())
            ->method('apply');

        ($this->handler)($command);
    }
}
