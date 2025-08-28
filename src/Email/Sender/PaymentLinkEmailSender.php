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
use Sylius\Component\Mailer\Sender\SenderInterface;

final class PaymentLinkEmailSender implements PaymentLinkEmailSenderInterface
{
    private const EMAIL_CODE = 'adyen_payment_link';

    public function __construct(
        private readonly SenderInterface $sender,
    ) {
    }

    public function send(PaymentLinkInterface $paymentLink, string $recipient): void
    {
        $this->sender->send(self::EMAIL_CODE, [$recipient], ['paymentLinkUrl' => $paymentLink->getPaymentLinkUrl()]);
    }
}
