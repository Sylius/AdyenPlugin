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

namespace Sylius\AdyenPlugin\Exception;

use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentLinkGenerationException extends \RuntimeException
{
    public static function create(PaymentInterface $payment, string $message = '', int $code = 0, ?\Throwable $previous = null): self
    {
        $message = trim(sprintf(
            'Payment link generation failed for payment with id %d. %s',
            $payment->getId(),
            $message,
        ));

        return new self($message, $code, $previous);
    }

    private function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
