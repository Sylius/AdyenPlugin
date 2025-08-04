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

use Sylius\AdyenPlugin\Bus\DispatcherInterface;
use Sylius\Component\Core\Model\PaymentInterface;

trait ProcessableResponseTrait
{
    /** @var DispatcherInterface */
    private $dispatcher;

    protected function dispatchPaymentStatusReceived(PaymentInterface $payment): void
    {
        $command = $this->dispatcher->getCommandFactory()->createForEvent(self::PAYMENT_STATUS_RECEIVED_CODE, $payment);
        $this->dispatcher->dispatch($command);
    }
}
