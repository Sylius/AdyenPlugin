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

class PaymentLink implements PaymentLinkInterface
{
    private ?int $id = null;

    private \DateTimeInterface $createdAt;

    public function __construct(
        private readonly PaymentInterface $payment,
        private readonly string $paymentLinkId,
        private readonly string $paymentLinkUrl,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaymentLinkId(): ?string
    {
        return $this->paymentLinkId;
    }

    public function getPaymentLinkUrl(): ?string
    {
        return $this->paymentLinkUrl;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }
}
