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
use Sylius\AdyenPlugin\Bus\Command\CreatePaymentDetailForPayment;
use Sylius\AdyenPlugin\Bus\Command\CreateReferenceForPayment;
use Sylius\AdyenPlugin\Bus\Command\PaymentStatusReceived;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\Bundle\ApiBundle\Command\Checkout\SendOrderConfirmation;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final class PaymentStatusReceivedHandler
{
    public const ALLOWED_EVENT_NAMES = ['authorised', 'redirectshopper', 'received'];

    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly RepositoryInterface $paymentRepository,
        private readonly RepositoryInterface $orderRepository,
        private readonly MessageBusInterface $commandBus,
        private readonly PaymentCommandFactoryInterface $commandFactory,
    ) {
    }

    public function __invoke(PaymentStatusReceived $command): void
    {
        $payment = $command->getPayment();
        $resultCode = $this->getResultCode($command->getPayment());
        $order = $payment->getOrder();
        Assert::isInstanceOf($order, OrderInterface::class);

        $this->commandBus->dispatch(new CreatePaymentDetailForPayment($payment));

        if ($this->isAccepted($resultCode)) {
            $this->updateOrderState($order);
        }

        try {
            $this->commandBus->dispatch(new CreateReferenceForPayment($payment));
            $this->paymentRepository->add($payment);

            $this->processCode($resultCode, $command);
        } catch (\InvalidArgumentException) {
            // probably redirect, we don't have a pspReference at this stage
        }
    }

    private function processCode(string $resultCode, PaymentStatusReceived $command): void
    {
        try {
            $subcommand = $this->commandFactory->createForEvent($resultCode, $command->getPayment());
            $this->commandBus->dispatch($subcommand);
        } catch (UnmappedAdyenActionException) {
            // nothing here
        }
    }

    private function updateOrderState(OrderInterface $order): void
    {
        if (!$this->stateMachine->can($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE)) {
            return;
        }

        $this->stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_COMPLETE);

        $this->orderRepository->add($order);

        $token = $order->getTokenValue();

        if (null !== $token) {
            $this->commandBus->dispatch(new SendOrderConfirmation($token));
        }
    }

    private function getResultCode(PaymentInterface $payment): string
    {
        $details = $payment->getDetails();

        return strtolower((string) $details['resultCode']);
    }

    private function isAccepted(string $resultCode): bool
    {
        return in_array($resultCode, self::ALLOWED_EVENT_NAMES, true);
    }
}
