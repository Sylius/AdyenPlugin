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

namespace Sylius\AdyenPlugin\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

class AdyenCredentials extends Constraint
{
    public string $messageInvalidApiKey = 'sylius_adyen.credentials.invalid_api_key';

    public string $messageInvalidMerchantAccount = 'sylius_adyen.credentials.invalid_merchant_account';

    public function validatedBy(): string
    {
        return 'sylius_adyen.validator.credentials';
    }
}
