<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AdyenPlugin\CommandHandler\AutoRescue;

use Sylius\AdyenPlugin\Command\AutoRescue\FlagPaymentRescueScheduled;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class FlagPaymentRescueScheduledHandler
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
    ) {
    }

    public function __invoke(FlagPaymentRescueScheduled $command): void
    {
//        /** @var PaymentInterface $payment */
//        $payment = $this->paymentRepository->find($command->paymentId);
//        if ($payment === null) {
//            return;
//        }

//        $details = $payment->getDetails() ?? [];
        $details['adyen']['rescueScheduled']     = true;
        $details['adyen']['merchantReference']   = $command->merchantReference;
        $details['adyen']['originalPspReference']= $command->pspReference;
        $details['adyen']['rescueReference']     = $command->rescueReference;
        $details['adyen']['flaggedForRescue'] = [
            'scheduled' => true,
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

//        $payment->setDetails($details);
    }
}
