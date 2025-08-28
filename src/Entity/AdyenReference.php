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

namespace Sylius\AdyenPlugin\Entity;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

class AdyenReference implements ResourceInterface, AdyenReferenceInterface, TimestampableInterface
{
    use TimestampableTrait;

    protected ?int $id;

    protected ?string $pspReference;

    protected ?PaymentInterface $payment;

    protected ?RefundPaymentInterface $refundPayment;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPspReference(): ?string
    {
        return $this->pspReference;
    }

    public function setPspReference(string $pspReference): void
    {
        $this->pspReference = $pspReference;
    }

    public function getPayment(): ?PaymentInterface
    {
        return $this->payment;
    }

    public function setPayment(?PaymentInterface $payment): void
    {
        $this->payment = $payment;
    }

    public function getRefundPayment(): ?RefundPaymentInterface
    {
        return $this->refundPayment;
    }

    public function setRefundPayment(?RefundPaymentInterface $refundPayment): void
    {
        $this->refundPayment = $refundPayment;
    }

    public function touch(): void
    {
        $this->setUpdatedAt(new \DateTime());
    }
}
