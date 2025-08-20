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
use Sylius\AdyenPlugin\Bus\Command\ReversePayment;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Client\ResponseStatus;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Webmozart\Assert\Assert;

final class ReversePaymentHandler
{
    public function __construct(
        private AdyenClientProviderInterface $adyenClientProvider,
        private StateMachineInterface $stateMachine,
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function __invoke(ReversePayment $command): void
    {
        $payment = $command->getPayment();

        if (
            !$this->adyenPaymentMethodChecker->isAdyenPayment($payment) ||
            !$this->stateMachine->can($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REVERSE)
        ) {
            return;
        }

        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        $client = $this->adyenClientProvider->getForPaymentMethod($method);
        $data = $client->requestReversal($payment);

        if (ResponseStatus::RECEIVED !== $data['status']) {
            throw new \RuntimeException(sprintf(
                'Reversal request for payment %s failed with status: %s',
                $payment->getId(),
                $data['status'],
            ));
        }

        $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REVERSE);
    }
}
