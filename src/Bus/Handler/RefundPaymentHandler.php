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

namespace Sylius\AdyenPlugin\Bus\Handler;

use Doctrine\ORM\EntityManagerInterface;
use SM\Factory\FactoryInterface;
use Sylius\AdyenPlugin\Bus\Command\RefundPayment;
use Sylius\AdyenPlugin\RefundPaymentTransitions as AdyenRefundPaymentTransitions;
use Sylius\RefundPlugin\StateResolver\RefundPaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RefundPaymentHandler
{
    public function __construct(
        private readonly FactoryInterface $stateMachineFactory,
        private readonly EntityManagerInterface $refundPaymentManager,
    ) {
    }

    public function __invoke(RefundPayment $command): void
    {
        $machine = $this->stateMachineFactory->get($command->getRefundPayment(), RefundPaymentTransitions::GRAPH);
        $machine->apply(AdyenRefundPaymentTransitions::TRANSITION_CONFIRM, true);

        $this->refundPaymentManager->persist($command->getRefundPayment());
        $this->refundPaymentManager->flush();
    }
}
