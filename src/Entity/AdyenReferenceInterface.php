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
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

interface AdyenReferenceInterface extends ResourceInterface
{
    public function getPspReference(): ?string;

    public function getRefundPayment(): ?RefundPaymentInterface;

    public function setRefundPayment(?RefundPaymentInterface $refundPayment): void;

    public function getPayment(): ?PaymentInterface;

    public function getId(): ?int;

    public function setPayment(?PaymentInterface $payment): void;

    public function setPspReference(string $pspReference): void;

    public function touch(): void;
}
