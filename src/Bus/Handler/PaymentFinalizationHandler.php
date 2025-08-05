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

use SM\Factory\FactoryInterface;
use Sylius\AdyenPlugin\Bus\Command\PaymentFinalizationCommand;
use Sylius\AdyenPlugin\Traits\OrderFromPaymentTrait;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PaymentFinalizationHandler
{
    use OrderFromPaymentTrait;

    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var RepositoryInterface */
    private $orderRepository;

    public function __construct(
        FactoryInterface $stateMachineFactory,
        RepositoryInterface $orderRepository,
    ) {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->orderRepository = $orderRepository;
    }

    private function updatePaymentState(PaymentInterface $payment, string $transition): void
    {
        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
        $stateMachine->apply($transition, true);
    }

    private function updatePayment(PaymentInterface $payment): void
    {
        $order = $payment->getOrder();
        if (null === $order) {
            return;
        }

        $this->orderRepository->add($order);
    }

    public function __invoke(PaymentFinalizationCommand $command): void
    {
        $payment = $command->getPayment();

        if (!$this->isAccepted($payment)) {
            return;
        }

        $this->updatePaymentState($payment, $command->getPaymentTransition());
        $this->updatePayment($payment);
    }

    private function isAccepted(PaymentInterface $payment): bool
    {
        return OrderPaymentStates::STATE_PAID !== $this->getOrderFromPayment($payment)->getPaymentState();
    }
}
