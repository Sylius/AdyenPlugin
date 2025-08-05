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
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Webmozart\Assert\Assert;

final class CreateReferenceForRefund
{
    /** @var PaymentInterface */
    private $payment;

    /** @var RefundPaymentInterface */
    private $refundPayment;

    /** @var string */
    private $refundReference;

    public function __construct(
        string $refundReference,
        RefundPaymentInterface $refundPayment,
        PaymentInterface $payment,
    ) {
        $details = $payment->getDetails();
        Assert::keyExists($details, 'pspReference', 'Payment pspReference is not present');

        $this->refundPayment = $refundPayment;
        $this->payment = $payment;
        $this->refundReference = $refundReference;
    }

    public function getRefundPayment(): RefundPaymentInterface
    {
        return $this->refundPayment;
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }

    public function getRefundReference(): string
    {
        return $this->refundReference;
    }
}
