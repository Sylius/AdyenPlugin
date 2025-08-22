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

namespace Sylius\AdyenPlugin\Factory;

use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Resource\Factory\FactoryInterface;

/** @phpstan-extends FactoryInterface<PaymentLinkInterface> */
interface PaymentLinkFactoryInterface extends FactoryInterface
{
    public function create(PaymentInterface $payment, string $paymentLinkId): PaymentLinkInterface;
}
