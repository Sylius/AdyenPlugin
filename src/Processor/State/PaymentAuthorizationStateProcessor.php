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

namespace Sylius\AdyenPlugin\Processor\State;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\PaymentTransitions;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class PaymentAuthorizationStateProcessor implements PaymentAuthorizeStateProcessorInterface
{
    public function __construct(
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(PaymentInterface $payment): void
    {
        $method = $payment->getMethod();
        if (!$method instanceof PaymentMethodInterface) {
            return;
        }

        $gatewayConfig = $method->getGatewayConfig();
        if (null === $gatewayConfig) {
            return;
        }

        if (AdyenClientProviderInterface::FACTORY_NAME !== $gatewayConfig->getFactoryName()) {
            return;
        }

        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CAPTURE)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CAPTURE);
            $this->entityManager->flush();
        }
    }
}
