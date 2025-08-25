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

use Sylius\AdyenPlugin\Bus\Command\CreatePaymentDetailForPayment;
use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\AdyenPlugin\Factory\AdyenPaymentDetailFactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreatePaymentDetailForPaymentHandler
{
    public function __construct(
        private readonly RepositoryInterface $adyenPaymentDetailRepository,
        private readonly AdyenPaymentDetailFactoryInterface $adyenPaymentDetailFactory,
    ) {
    }

    public function __invoke(CreatePaymentDetailForPayment $command): void
    {
        $payment = $command->payment;
        /** @var AdyenPaymentDetailInterface $paymentDetail */
        $paymentDetail = $this->adyenPaymentDetailRepository->findOneBy(['payment' => $payment]);

        if (null === $paymentDetail) {
            $paymentDetail = $this->adyenPaymentDetailFactory->createForPayment($payment);
        } else {
            $paymentDetail->setAmount($payment->getAmount());
        }

        $this->adyenPaymentDetailRepository->add($paymentDetail);
    }
}
