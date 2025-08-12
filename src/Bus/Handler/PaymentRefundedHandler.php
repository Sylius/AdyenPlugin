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
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\Command\PaymentRefundedCommand;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Factory\RefundPaymentFactoryInterface;
use Sylius\RefundPlugin\StateResolver\RefundPaymentTransitions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final class PaymentRefundedHandler
{
    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly RefundPaymentFactoryInterface $refundPaymentFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(PaymentRefundedCommand $command): void
    {
        $payment = $command->getPayment();
        $notificationData = $command->getNotificationData();

        // Create RefundPayment entity
        $this->createRefundPayment($payment, $notificationData);

        // Update payment state
        $this->updatePaymentState($payment);
    }

    private function createRefundPayment(PaymentInterface $payment, NotificationItemData $notificationData): void
    {
        $order = $payment->getOrder();
        Assert::isInstanceOf($order, OrderInterface::class);

        $paymentMethod = $payment->getMethod();
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        $amount = $notificationData->amount->value ?? $payment->getAmount();
        $currencyCode = $notificationData->amount->currency ?? $payment->getCurrencyCode();

        $refundPayment = $this->refundPaymentFactory->createWithData(
            $order,
            $amount,
            $currencyCode,
            RefundPaymentInterface::STATE_COMPLETED, // Already processed by Adyen
            $paymentMethod,
        );

        $this->entityManager->persist($refundPayment);
        $this->entityManager->flush();

        if ($this->stateMachine->can($refundPayment, RefundPaymentTransitions::GRAPH, RefundPaymentTransitions::TRANSITION_COMPLETE)) {
            $this->stateMachine->apply($refundPayment, RefundPaymentTransitions::GRAPH, RefundPaymentTransitions::TRANSITION_COMPLETE);
        }
    }

    private function updatePaymentState(PaymentInterface $payment): void
    {
        if ($this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND)) {
            $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_REFUND);
        }
    }
}
