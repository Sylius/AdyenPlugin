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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\RefundPayment;
use Sylius\AdyenPlugin\Bus\Handler\RefundPaymentHandler;
use Sylius\AdyenPlugin\RefundPaymentTransitions;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\RefundPlugin\Entity\RefundPayment as RefundPaymentEntity;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\StateResolver\RefundPaymentTransitions as BaseRefundPaymentTransitions;

class RefundPaymentHandlerTest extends TestCase
{
    private MockObject|RepositoryInterface $refundPaymentManager;

    private MockObject|StateMachineInterface $stateMachine;

    private RefundPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->refundPaymentManager = $this->createMock(EntityManagerInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);

        $this->handler = new RefundPaymentHandler($this->stateMachine, $this->refundPaymentManager);
    }

    #[DataProvider('provideForTestProcess')]
    public function testProcess(bool $canTransition): void
    {
        $entity = new RefundPaymentEntity(
            $this->createMock(OrderInterface::class),
            42,
            'EUR',
            RefundPaymentInterface::STATE_NEW,
            new PaymentMethod(),
        );

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with(
                $entity,
                BaseRefundPaymentTransitions::GRAPH,
                RefundPaymentTransitions::TRANSITION_CONFIRM,
            )
            ->willReturn($canTransition)
        ;

        $this->stateMachine
            ->expects($canTransition ? $this->once() : $this->never())
            ->method('apply')
            ->with(
                $entity,
                BaseRefundPaymentTransitions::GRAPH,
                RefundPaymentTransitions::TRANSITION_CONFIRM,
            )
        ;

        $this->refundPaymentManager
            ->expects($this->once())
            ->method('persist')
            ->with(
                $this->equalTo($entity),
            )
        ;

        $command = new RefundPayment($entity);
        ($this->handler)($command);
    }

    public static function provideForTestProcess(): \Generator
    {
        yield 'can transition' => [true];
        yield 'cannot transition' => [false];
    }
}
