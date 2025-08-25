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

namespace Sylius\AdyenPlugin\Bus;

use Sylius\AdyenPlugin\Bus\Command\AuthorizePayment;
use Sylius\AdyenPlugin\Bus\Command\AuthorizePaymentByLink;
use Sylius\AdyenPlugin\Bus\Command\CapturePayment;
use Sylius\AdyenPlugin\Bus\Command\MarkPaymentAsProcessedCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentCancelledCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentFailedCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentRefunded;
use Sylius\AdyenPlugin\Bus\Command\PaymentStatusReceived;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentCommandFactoryInterface
{
    public const MAPPING = [
        'authorisation' => AuthorizePayment::class,
        'payment_status_received' => PaymentStatusReceived::class,
        'capture' => CapturePayment::class,
        'received' => MarkPaymentAsProcessedCommand::class,
        'refused' => PaymentFailedCommand::class,
        'rejected' => PaymentFailedCommand::class,
        'cancellation' => PaymentCancelledCommand::class,
        'refund' => PaymentRefunded::class,
        'pay_by_link_authorisation' => AuthorizePaymentByLink::class,
    ];

    public function createForEvent(
        string $event,
        PaymentInterface $payment,
        ?NotificationItemData $notificationItemData = null,
    ): object;
}
