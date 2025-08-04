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
use Sylius\AdyenPlugin\Bus\Command\RefundPayment;
use Sylius\AdyenPlugin\Bus\Handler\RefundPaymentHandler;
use Sylius\AdyenPlugin\RefundPaymentTransitions;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\RefundPlugin\Entity\RefundPayment as RefundPaymentEntity;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

class RefundPaymentHandlerTest extends TestCase
{
    use StateMachineTrait;

    /** @var EntityManagerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $refundPaymentManager;

    /** @var RefundPaymentHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->setupStateMachineMocks();

        $this->refundPaymentManager = $this->createMock(EntityManagerInterface::class);
        $this->handler = new RefundPaymentHandler($this->stateMachineFactory, $this->refundPaymentManager);
    }

    public function testProcess(): void
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
            ->method('apply')
            ->with(
                $this->equalTo(RefundPaymentTransitions::TRANSITION_CONFIRM),
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
}
