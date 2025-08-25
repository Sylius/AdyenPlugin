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

use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface AdyenReferenceRepositoryInterface extends RepositoryInterface
{
    public function getOneByPaymentMethodCodeAndReference(string $paymentMethodCode, string $pspReference): ?AdyenReferenceInterface;

    public function getOneForRefundByCodeAndReference(string $code, string $pspReference): AdyenReferenceInterface;

    public function findAllByPayment(PaymentInterface $payment): array;
}
