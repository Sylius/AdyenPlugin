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

namespace Sylius\AdyenPlugin\Processor;

use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;

interface PaymentResponseProcessorInterface
{
    public function process(
        string $code,
        Request $request,
        PaymentInterface $payment,
    ): string;
}
