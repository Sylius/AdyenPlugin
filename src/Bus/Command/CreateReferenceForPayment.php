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

namespace Sylius\AdyenPlugin\Bus\Command;

use Sylius\Component\Core\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class CreateReferenceForPayment
{
    public function __construct(private readonly PaymentInterface $payment)
    {
        $details = $payment->getDetails();
        Assert::keyExists($details, 'pspReference', 'Payment pspReference is not present');
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }
}
