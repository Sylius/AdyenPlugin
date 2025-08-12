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

namespace Tests\Sylius\AdyenPlugin\Unit\Bus\Command;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\PaymentRefundedCommand;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Component\Core\Model\PaymentInterface;

class PaymentRefundedCommandTest extends TestCase
{
    public function testConstruct(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $notificationData = new NotificationItemData();

        $command = new PaymentRefundedCommand($payment, $notificationData);

        $this->assertSame($payment, $command->getPayment());
        $this->assertSame($notificationData, $command->getNotificationData());
    }

    public function testGetPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $notificationData = new NotificationItemData();

        $command = new PaymentRefundedCommand($payment, $notificationData);

        $this->assertSame($payment, $command->getPayment());
    }

    public function testGetNotificationData(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = 'CANCEL_OR_REFUND';

        $command = new PaymentRefundedCommand($payment, $notificationData);

        $this->assertSame($notificationData, $command->getNotificationData());
        $this->assertEquals('CANCEL_OR_REFUND', $command->getNotificationData()->eventCode);
    }
}
