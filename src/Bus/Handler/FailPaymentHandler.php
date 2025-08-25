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
use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\FailPayment;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class FailPaymentHandler
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly AdyenReferenceRepositoryInterface $adyenReferenceRepository,
    ) {
    }

    public function __invoke(FailPayment $command): void
    {
        $paymentReference = $this->adyenReferenceRepository->getOneByPaymentMethodCodeAndReference($command->paymentMethodCode, $command->pspReference);

        if ($paymentReference === null) {
            return;
        }

        $payment = $paymentReference->getPayment();

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_FAIL);
        }
    }
}
