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

namespace Sylius\AdyenPlugin\Repository;

use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface AdyenTokenRepositoryInterface extends RepositoryInterface
{
    public function findOneByPaymentMethodAndCustomer(
        PaymentMethodInterface $paymentMethod,
        CustomerInterface $customer,
    ): ?AdyenTokenInterface;
}
