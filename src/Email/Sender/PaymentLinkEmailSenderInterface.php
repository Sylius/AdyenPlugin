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

namespace Sylius\AdyenPlugin\Email\Sender;

use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;

interface PaymentLinkEmailSenderInterface
{
    public function send(PaymentLinkInterface $paymentLink, string $recipient): void;
}
