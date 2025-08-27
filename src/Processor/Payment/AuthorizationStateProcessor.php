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

namespace Sylius\AdyenPlugin\Processor\Payment;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\PaymentInterface;

final class AuthorizationStateProcessor implements AuthorizationStateProcessorInterface
{
    public function __construct(
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(PaymentInterface $payment): void
    {
        if (!$this->adyenPaymentMethodChecker->isAdyenPayment($payment)) {
            return;
        }
        if ($this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::MANUAL)) {
            return;
        }

        if ($this->stateMachine->can($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CAPTURE)) {
            $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_CAPTURE);
            $this->entityManager->flush();
        }
    }
}
