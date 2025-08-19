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
use Sylius\AdyenPlugin\Bus\Command\PaymentRefunded;
use Sylius\AdyenPlugin\Factory\AdyenReferenceFactoryInterface;
use Sylius\AdyenPlugin\RefundPaymentTransitions as AdyenRefundPaymentTransitions;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
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
        private readonly AdyenReferenceFactoryInterface $referenceFactory,
        private readonly AdyenReferenceRepositoryInterface $referenceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(PaymentRefunded $command): void
    {
        $payment = $command->getPayment();
        $notificationData = $command->getNotificationData();

        $refundPayment = $this->resolveRefundPayment($payment, $notificationData);

        if (null === $refundPayment) {
            return;
        }

        if ($this->stateMachine->can($refundPayment, RefundPaymentTransitions::GRAPH, AdyenRefundPaymentTransitions::TRANSITION_CONFIRM)) {
            $this->stateMachine->apply($refundPayment, RefundPaymentTransitions::GRAPH, AdyenRefundPaymentTransitions::TRANSITION_CONFIRM);
        }
    }

    private function resolveRefundPayment(
        PaymentInterface $payment,
        NotificationItemData $notificationData,
    ): ?RefundPaymentInterface {
        $order = $payment->getOrder();
        Assert::isInstanceOf($order, OrderInterface::class);

        $paymentMethod = $payment->getMethod();
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        $refundPspReference = $notificationData->pspReference;

        try {
            $reference = $this->referenceRepository->getOneForRefundByCodeAndReference(
                $paymentMethod->getCode(),
                $refundPspReference,
            );

            return $reference->getRefundPayment();
        } catch (\Exception) {
            $paymentDetails = $payment->getDetails();
            if (
                !isset($paymentDetails['pspReference']) ||
                $notificationData->originalReference !== $paymentDetails['pspReference']
            ) {
                return null;
            }

            return $this->createRefundPaymentWithReference(
                $order,
                $payment,
                $paymentMethod,
                $refundPspReference,
                $notificationData->amount->value ?? $payment->getAmount(),
                $notificationData->amount->currency ?? $payment->getCurrencyCode(),
            );
        }
    }

    private function createRefundPaymentWithReference(
        OrderInterface $order,
        PaymentInterface $payment,
        PaymentMethodInterface $paymentMethod,
        string $refundPspReference,
        int $amount,
        string $currencyCode,
    ): RefundPaymentInterface {
        $refundPayment = $this->refundPaymentFactory->createWithData(
            $order,
            $amount,
            $currencyCode,
            RefundPaymentInterface::STATE_NEW,
            $paymentMethod,
        );

        $reference = $this->referenceFactory->createForRefund(
            $refundPspReference,
            $payment,
            $refundPayment,
        );

        $this->entityManager->persist($refundPayment);
        $this->entityManager->persist($reference);
        $this->entityManager->flush();

        return $refundPayment;
    }
}
