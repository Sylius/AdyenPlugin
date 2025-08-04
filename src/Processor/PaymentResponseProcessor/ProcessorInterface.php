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

namespace Sylius\AdyenPlugin\Processor\PaymentResponseProcessor;

use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;

interface ProcessorInterface
{
    public function accepts(Request $request, ?PaymentInterface $payment): bool;

    public function process(
        string $code,
        Request $request,
        PaymentInterface $payment,
    ): string;
}
