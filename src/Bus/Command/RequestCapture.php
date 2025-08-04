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

use Sylius\Component\Core\Model\OrderInterface;

final class RequestCapture implements AlterPaymentCommand
{
    /** @var OrderInterface */
    private $payment;

    public function __construct(OrderInterface $payment)
    {
        $this->payment = $payment;
    }

    public function getOrder(): OrderInterface
    {
        return $this->payment;
    }
}
