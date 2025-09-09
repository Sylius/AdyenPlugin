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

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\AdyenPlugin\Bus\Command\PaymentCancelledCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentFailedCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentFinalizationCommand;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PaymentFinalizationHandler
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
    ) {
    }

    public function __invoke(PaymentFinalizationCommand $command): void
    {
        if (!$this->isAccepted($command)) {
            return;
        }

        $payment = $command->getPayment();

        $this->updatePaymentState($payment, $command->getPaymentTransition());

        if (is_a($command, PaymentCancelledCommand::class, true)) {
            $details = $payment->getDetails();
            unset($details[CancelPayment::PROCESSING_CANCELLATION]);
            $payment->setDetails($details);
        }
    }

    private function updatePaymentState(PaymentInterface $payment, string $transition): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
        }
    }

    private function isAccepted(PaymentFinalizationCommand $command): bool
    {
        $payment = $command->getPayment();
        if ($command instanceof PaymentFailedCommand && $payment->getState() !== PaymentInterface::STATE_FAILED) {
            return true;
        }

        return
            OrderPaymentStates::STATE_PAID !== $payment->getOrder()?->getPaymentState() ||
            $payment->getState() === PaymentGraph::STATE_PROCESSING_REVERSAL ||
            (
                $payment->getState() === PaymentInterface::STATE_PROCESSING &&
                ($payment->getDetails()[CancelPayment::PROCESSING_CANCELLATION] ?? false)
            )
        ;
    }
}
