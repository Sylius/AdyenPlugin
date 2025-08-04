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
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Traits\PayableOrderPaymentTrait;
use Sylius\AdyenPlugin\Traits\PaymentFromOrderTrait;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TakeOverPaymentHandler
{
    use PayableOrderPaymentTrait;
    use PaymentFromOrderTrait;

    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    /** @var EntityManagerInterface */
    private $paymentManager;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        EntityManagerInterface $paymentManager,
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentManager = $paymentManager;
    }

    private function persistPayment(PaymentInterface $payment): void
    {
        $this->paymentManager->persist($payment);
        $this->paymentManager->flush();
    }

    public function __invoke(TakeOverPayment $command): void
    {
        $payment = $this->getPayablePayment($command->getOrder());
        $method = $this->getMethod($payment);

        if ($method->getCode() === $command->getPaymentCode()) {
            return;
        }

        $paymentMethod = $this->paymentMethodRepository->getOneForAdyenAndCode($command->getPaymentCode());
        $payment->setMethod($paymentMethod);

        $this->persistPayment($payment);
    }
}
