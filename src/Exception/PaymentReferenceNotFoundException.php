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

namespace Sylius\AdyenPlugin\Exception;

final class PaymentReferenceNotFoundException extends \RuntimeException
{
    public function __construct(string $paymentMethodCode, string $merchantReference, ?string $pspReference = null)
    {
        parent::__construct(
            sprintf(
                'Payment reference not found for payment method "%s" with merchant reference "%s" and PSP reference "%s".',
                $paymentMethodCode,
                $merchantReference,
                $pspReference ?? 'N/A',
            ),
        );
    }
}
