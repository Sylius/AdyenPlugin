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

namespace Sylius\AdyenPlugin\Bus\Command;

use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentRefundedCommand
{
    /** @var PaymentInterface */
    private $payment;

    /** @var NotificationItemData */
    private $notificationData;

    public function __construct(PaymentInterface $payment, NotificationItemData $notificationData)
    {
        $this->payment = $payment;
        $this->notificationData = $notificationData;
    }

    public function getPayment(): PaymentInterface
    {
        return $this->payment;
    }

    public function getNotificationData(): NotificationItemData
    {
        return $this->notificationData;
    }
}
