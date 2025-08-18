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

namespace Sylius\AdyenPlugin\CommandHandler\AutoRescue;

use Sylius\AdyenPlugin\Command\AutoRescue\AutoRescueSuccess;
use Sylius\AdyenPlugin\Command\AutoRescue\FlagPaymentRescueScheduled;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AutoRescueSuccessHandler
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
    ) {
    }

    public function __invoke(AutoRescueSuccess $command): void
    {
        /** @var PaymentInterface $payment */
        $payment = $this->paymentRepository->find($command->paymentId);
        if ($payment === null) {
            return;
        }
    }
}
