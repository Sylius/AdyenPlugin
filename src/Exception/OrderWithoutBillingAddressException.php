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

use Sylius\Component\Core\Model\OrderInterface;

final class OrderWithoutBillingAddressException extends \RuntimeException
{
    public function __construct(OrderInterface $order, ?\Throwable $previous = null)
    {
        parent::__construct(
            sprintf('The order %d has no billing address set.', (int) $order->getId()),
            0,
            $previous,
        );
    }
}
