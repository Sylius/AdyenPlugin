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

interface AdyenPaymentDetailInterface extends ResourceInterface
{
    public function getAmount(): int;

    public function setAmount(int $amount): void;

    public function getPayment(): ?PaymentInterface;

    public function setPayment(PaymentInterface $payment): void;
}
