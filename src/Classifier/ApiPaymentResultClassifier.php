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

final class ApiPaymentResultClassifier implements PaymentResultClassifierInterface
{
    public function classify(mixed $paymentId, array $input): PaymentResult
    {
        $code = strtolower($input['resultCode']);
        $type = match ($code) {
            'authorised' => PaymentResultType::Authorised,
            'received','processing','pending','redirectshopper','identifyshopper','challengeshopper','presenttoshopper'
            => PaymentResultType::Pending,
            default => PaymentResultType::Failed,
        };

        return new PaymentResult($paymentId, $type);
    }
}
