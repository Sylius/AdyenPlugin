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

use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;

class AdyenPaymentDetail implements ResourceInterface, TimestampableInterface, AdyenPaymentDetailInterface
{
    use TimestampableTrait;

    protected ?int $id;

    protected int $amount;

    protected string $captureMode = PaymentCaptureMode::AUTOMATIC;

    protected PaymentInterface $payment;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getCaptureMode(): string
    {
        return $this->captureMode;
    }

    public function setCaptureMode(string $captureMode): void
    {
        $this->captureMode = $captureMode;
    }

    public function getPayment(): ?PaymentInterface
    {
        return $this->payment;
    }

    public function setPayment(PaymentInterface $payment): void
    {
        $this->payment = $payment;
    }
}
