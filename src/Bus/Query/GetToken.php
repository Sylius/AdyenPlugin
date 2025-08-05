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

namespace Sylius\AdyenPlugin\Bus\Query;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class GetToken
{
    /** @var OrderInterface */
    private $order;

    /** @var PaymentMethodInterface */
    private $paymentMethod;

    public function __construct(PaymentMethodInterface $paymentMethod, OrderInterface $order)
    {
        $this->order = $order;
        $this->paymentMethod = $paymentMethod;
    }

    public function getOrder(): OrderInterface
    {
        return $this->order;
    }

    public function getPaymentMethod(): PaymentMethodInterface
    {
        return $this->paymentMethod;
    }
}
