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

use Sylius\AdyenPlugin\Bus\Command\CreateReferenceForRefund;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Repository\RefundPaymentRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final class RefundPaymentGeneratedHandler
{
    public function __construct(
        private readonly AdyenClientProviderInterface $adyenClientProvider,
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly RefundPaymentRepositoryInterface $refundPaymentRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    public function __invoke(RefundPaymentGenerated $refundPaymentGenerated): void
    {
        /** @var PaymentInterface $payment */
        $payment = $this->paymentRepository->find($refundPaymentGenerated->paymentId());
        $paymentMethod = $this->paymentMethodRepository->find($refundPaymentGenerated->paymentMethodId());

        if (
            null === $payment ||
            null === $paymentMethod ||
            !$this->adyenPaymentMethodChecker->isAdyenPaymentMethod($paymentMethod)
        ) {
            return;
        }

        $adyenReference = $this->sendRefundRequest($refundPaymentGenerated, $paymentMethod, $payment);
        $this->createReference($adyenReference, $refundPaymentGenerated, $payment);
    }

    private function createReference(
        string $newReference,
        RefundPaymentGenerated $refundPaymentGenerated,
        PaymentInterface $payment,
    ): void {
        /** @var RefundPaymentInterface $refund */
        $refund = $this->refundPaymentRepository->find($refundPaymentGenerated->id());
        if (null === $refund) {
            return;
        }

        $this->messageBus->dispatch(new CreateReferenceForRefund($newReference, $refund, $payment));
    }

    private function sendRefundRequest(
        RefundPaymentGenerated $refundPaymentGenerated,
        PaymentMethodInterface $paymentMethod,
        PaymentInterface $payment,
    ): string {
        Assert::keyExists(
            $payment->getDetails(),
            'pspReference',
            'Payment has not been initialized by Adyen',
        );

        $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);

        $result = $client->requestRefund($payment, $refundPaymentGenerated);

        Assert::keyExists($result, 'pspReference');

        return (string) $result['pspReference'];
    }
}
