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

use Sylius\AdyenPlugin\Bus\Command\NotificationDataAwarePaymentCommand;
use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class PaymentCommandFactory implements PaymentCommandFactoryInterface
{
    public function __construct(
        private EventCodeResolverInterface $eventCodeResolver,
        private array $mapping = [],
    ) {
        $this->mapping = array_merge_recursive(self::MAPPING, $mapping);
    }

    public function createForEvent(
        string $event,
        PaymentInterface $payment,
        ?NotificationItemData $notificationItemData = null,
    ): object {
        if (null !== $notificationItemData) {
            $event = $this->eventCodeResolver->resolve($notificationItemData);
        }

        return $this->createObject($event, $payment, $notificationItemData);
    }

    private function createObject(
        string $eventName,
        PaymentInterface $payment,
        ?NotificationItemData $notificationItemData = null,
    ): NotificationDataAwarePaymentCommand|PaymentLifecycleCommand {
        if (!isset($this->mapping[$eventName])) {
            throw new UnmappedAdyenActionException(sprintf('Event "%s" has no handler registered', $eventName));
        }

        $class = (string) $this->mapping[$eventName];

        if (is_a($class, NotificationDataAwarePaymentCommand::class, true)) {
            return new $class($payment, $notificationItemData);
        }

        Assert::isAOf($class, PaymentLifecycleCommand::class);

        return new $class($payment);
    }
}
