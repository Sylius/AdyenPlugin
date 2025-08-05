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

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface AdyenTokenInterface extends ResourceInterface
{
    public function setCustomer(?CustomerInterface $customer): void;

    public function getCustomer(): ?CustomerInterface;

    public function setIdentifier(?string $identifier): void;

    public function getIdentifier(): ?string;

    public function setPaymentMethod(?PaymentMethodInterface $paymentMethod): void;

    public function getPaymentMethod(): ?PaymentMethodInterface;
}
