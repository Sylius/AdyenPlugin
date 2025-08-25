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

use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/** @phpstan-extends RepositoryInterface<PaymentLinkInterface> */
interface PaymentLinkRepositoryInterface extends RepositoryInterface
{
    public function findOneByPaymentAndLinkId(PaymentInterface $payment, string $paymentLinkId): ?PaymentLinkInterface;

    public function findOneByPaymentMethodCodeAndLinkId(string $paymentMethodCode, string $paymentLinkId): ?PaymentLinkInterface;

    public function removeByLinkId(string $paymentLinkId): void;
}
