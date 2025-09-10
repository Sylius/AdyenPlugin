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
use Sylius\AdyenPlugin\Client\ResponseStatus;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;

interface PaymentCommandFactoryInterface
{
    public const MAPPING = [
        EventCodeResolverInterface::EVENT_AUTHORIZATION => AuthorizePayment::class,
        EventCodeResolverInterface::EVENT_CANCELLATION => PaymentCancelledCommand::class,
        EventCodeResolverInterface::EVENT_CAPTURE => CapturePayment::class,
        EventCodeResolverInterface::EVENT_CAPTURE_FAILED => PaymentFailedCommand::class,
        EventCodeResolverInterface::EVENT_PAY_BY_LINK_AUTHORISATION => AuthorizePaymentByLink::class,
        EventCodeResolverInterface::EVENT_REFUND => PaymentRefunded::class,
        ResponseStatus::AUTHORISED => AuthorizePayment::class, // Special case for external page completion, sync
        ResponseStatus::PAYMENT_STATUS_RECEIVED => PaymentStatusReceived::class,
        ResponseStatus::RECEIVED => MarkPaymentAsProcessedCommand::class,
        ResponseStatus::REFUSED => PaymentFailedCommand::class,
        ResponseStatus::REJECTED => PaymentFailedCommand::class,
    ];

    public function createForEvent(
        string $event,
        PaymentInterface $payment,
        ?NotificationItemData $notificationItemData = null,
    ): object;
}
