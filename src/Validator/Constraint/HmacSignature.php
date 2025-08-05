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

class HmacSignature extends Constraint
{
    /** @var string */
    public $message = 'sylius_adyen.runtime.invalid_signature';

    public function getTargets()
    {
        return parent::CLASS_CONSTRAINT;
    }
}
