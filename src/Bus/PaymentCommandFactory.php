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

use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\AdyenPlugin\Exception\UnmappedAdyenActionException;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Webmozart\Assert\Assert;

final class PaymentCommandFactory implements PaymentCommandFactoryInterface
{
    /** @var array */
    private $mapping = [];

    /** @var EventCodeResolverInterface */
    private $eventCodeResolver;

    public function __construct(
        EventCodeResolverInterface $eventCodeResolver,
        array $mapping = [],
    ) {
        $this->mapping = array_merge_recursive(self::MAPPING, $mapping);
        $this->eventCodeResolver = $eventCodeResolver;
    }

    public function createForEvent(
        string $event,
        PaymentInterface $payment,
        ?NotificationItemData $notificationItemData = null,
    ): PaymentLifecycleCommand {
        if (null !== $notificationItemData) {
            $event = $this->eventCodeResolver->resolve($notificationItemData);
        }

        return $this->createObject($event, $payment);
    }

    private function createObject(string $eventName, PaymentInterface $payment): PaymentLifecycleCommand
    {
        if (!isset($this->mapping[$eventName])) {
            throw new UnmappedAdyenActionException(sprintf('Event "%s" has no handler registered', $eventName));
        }

        $class = (string) $this->mapping[$eventName];

        $result = new $class($payment);
        Assert::isInstanceOf($result, PaymentLifecycleCommand::class);

        return $result;
    }
}
