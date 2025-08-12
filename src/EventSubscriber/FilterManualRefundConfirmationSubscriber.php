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

namespace Sylius\AdyenPlugin\EventSubscriber;

use SM\Event\SMEvents;
use SM\Event\TransitionEvent;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodChecker;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Sylius\RefundPlugin\StateResolver\RefundPaymentTransitions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class FilterManualRefundConfirmationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SMEvents::TEST_TRANSITION => 'filter',
        ];
    }

    public function filter(TransitionEvent $event): void
    {
        if (
            RefundPaymentTransitions::GRAPH !== $event->getStateMachine()->getGraph() ||
            RefundPaymentTransitions::TRANSITION_COMPLETE !== $event->getTransition()
        ) {
            return;
        }

        /** @var RefundPaymentInterface $object */
        $object = $event->getStateMachine()->getObject();
        if (AdyenPaymentMethodChecker::isAdyenPaymentMethod($object->getPaymentMethod())) {
            $event->setRejected();
        }
    }
}
