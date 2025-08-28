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

use Sylius\AdyenPlugin\Entity\PaymentLink;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Resource\Exception\UnsupportedMethodException;

final class PaymentLinkFactory implements PaymentLinkFactoryInterface
{
    public function createNew(): never
    {
        throw new UnsupportedMethodException(__METHOD__);
    }

    public function create(
        PaymentInterface $payment,
        string $paymentLinkId,
        string $paymentLinkUrl,
    ): PaymentLinkInterface {
        return new PaymentLink($payment, $paymentLinkId, $paymentLinkUrl);
    }
}
