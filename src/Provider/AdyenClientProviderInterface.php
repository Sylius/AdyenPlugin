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

namespace Sylius\AdyenPlugin\Provider;

use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

interface AdyenClientProviderInterface
{
    public const FACTORY_NAME = 'adyen';

    public function getDefaultClient(): AdyenClientInterface;

    public function getClientForCode(string $code): AdyenClientInterface;

    public function getForPaymentMethod(PaymentMethodInterface $paymentMethod): AdyenClientInterface;
}
