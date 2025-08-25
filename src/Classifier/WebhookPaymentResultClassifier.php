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

namespace Sylius\AdyenPlugin\Classifier;

use Sylius\AdyenPlugin\Dto\PaymentResult;
use Sylius\AdyenPlugin\Enum\PaymentResultType;

final class WebhookPaymentResultClassifier implements PaymentResultClassifierInterface
{
    public function classify(mixed $paymentId, array $input): PaymentResult
    {
        $eventCode = strtoupper($input['eventCode']);
        $success   = (bool)($input['success'] ?? false);

        $type = match ($eventCode) {
            'AUTHORISATION' => $success ? PaymentResultType::Authorised : PaymentResultType::Failed,
            'CANCELLATION'  => PaymentResultType::Failed,
            default         => PaymentResultType::Pending,
        };

        return new PaymentResult($paymentId, $type);
    }
}
