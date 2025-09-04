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
use Sylius\AdyenPlugin\Bus\Command\AlterPaymentCommand;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\AdyenPlugin\Bus\Command\RequestCapture;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final class AlterPaymentHandler
{
    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private StateMachineInterface $stateMachine,
    ) {
    }

    public function __invoke(AlterPaymentCommand $alterPaymentCommand): void
    {
        $payment = $this->getPayment($alterPaymentCommand->getOrder());

        if (
            null === $payment ||
            !$this->adyenPaymentMethodChecker->isAdyenPayment($payment) ||
            !$this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::MANUAL)
        ) {
            return;
        }

        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        $client = $this->adyenClientProvider->getForPaymentMethod($method);
        $this->dispatchRemoteAction($payment, $alterPaymentCommand, $client);

        $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS);
    }

    private function dispatchRemoteAction(
        PaymentInterface $payment,
        AlterPaymentCommand $alterPaymentCommand,
        AdyenClientInterface $adyenClient,
    ): void {
        if ($alterPaymentCommand instanceof RequestCapture) {
            $adyenClient->requestCapture($payment);
        }

        if ($alterPaymentCommand instanceof CancelPayment) {
            $adyenClient->requestCancellation($payment);

            $paymentDetails = $payment->getDetails();
            $paymentDetails[CancelPayment::PROCESSING_CANCELLATION] = true;
            $payment->setDetails($paymentDetails);
        }
    }

    private function getPayment(OrderInterface $order): ?PaymentInterface
    {
        if (!$this->isAuthorized($order)) {
            return null;
        }

        $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);
        if (null === $payment) {
            return null;
        }

        return $payment;
    }

    private function isAuthorized(OrderInterface $order): bool
    {
        return OrderPaymentStates::STATE_AUTHORIZED === $order->getPaymentState();
    }
}
