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
use Sylius\AdyenPlugin\Bus\Command\TakeOverPayment;
use Sylius\AdyenPlugin\Clearer\PaymentReferencesClearerInterface;
use Sylius\AdyenPlugin\Exception\AdyenPaymentMethodNotFoundException;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Traits\PayableOrderPaymentTrait;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TakeOverPaymentHandler
{
    use PayableOrderPaymentTrait;

    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentReferencesClearerInterface $paymentReferencesClearer,
        private readonly EntityManagerInterface $paymentManager,
    ) {
    }

    public function __invoke(TakeOverPayment $command): void
    {
        $payment = $this->getPayablePayment($command->getOrder());
        /** @var PaymentMethodInterface $method */
        $method = $payment->getMethod();

        if ($method->getCode() === $command->getPaymentCode()) {
            return;
        }

        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($command->getPaymentCode());
        if (null === $paymentMethod) {
            throw new AdyenPaymentMethodNotFoundException($command->getPaymentCode());
        }

        $this->paymentReferencesClearer->clear($payment);
        $payment->setMethod($paymentMethod);

        $this->persistPayment($payment);
    }

    private function persistPayment(PaymentInterface $payment): void
    {
        $this->paymentManager->persist($payment);
        $this->paymentManager->flush();
    }
}
