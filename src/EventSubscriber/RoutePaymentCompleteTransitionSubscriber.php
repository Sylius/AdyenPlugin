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
use Sylius\AdyenPlugin\Bus\Command\RequestCapture;
use Sylius\AdyenPlugin\Bus\DispatcherInterface;
use Sylius\AdyenPlugin\Exception\UnprocessablePaymentException;
use Sylius\AdyenPlugin\PaymentTransitions;
use Sylius\AdyenPlugin\Traits\OrderFromPaymentTrait;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class RoutePaymentCompleteTransitionSubscriber implements EventSubscriberInterface
{
    use OrderFromPaymentTrait;

    /** @var DispatcherInterface */
    private $dispatcher;

    public function __construct(
        DispatcherInterface $dispatcher,
    ) {
        $this->dispatcher = $dispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SMEvents::PRE_TRANSITION => 'doFilter',
            SMEvents::TEST_TRANSITION => 'canComplete',
        ];
    }

    private function isProcessableAdyenPayment(TransitionEvent $event): bool
    {
        if (PaymentTransitions::GRAPH !== $event->getStateMachine()->getGraph()) {
            return false;
        }

        if (PaymentTransitions::TRANSITION_COMPLETE !== $event->getTransition()) {
            return false;
        }
        if (!isset($this->getObject($event)->getDetails()['pspReference'])) {
            return false;
        }

        return true;
    }

    private function getObject(TransitionEvent $event): PaymentInterface
    {
        /**
         * @var ?PaymentInterface $object
         */
        $object = $event->getStateMachine()->getObject();
        if (null === $object) {
            throw new UnprocessablePaymentException();
        }

        return $object;
    }

    public function canComplete(TransitionEvent $event): void
    {
        if (
            !$this->isProcessableAdyenPayment($event) ||
            PaymentInterface::STATE_PROCESSING !== $event->getState() ||
            PaymentTransitions::TRANSITION_CAPTURE === $event->getTransition()
        ) {
            return;
        }

        $event->setRejected();
    }

    public function doFilter(TransitionEvent $event): void
    {
        if (!$this->isProcessableAdyenPayment($event)) {
            return;
        }

        $this->dispatcher->dispatch(
            new RequestCapture(
                $this->getOrderFromPayment(
                    $this->getObject($event),
                ),
            ),
        );

        $event->setRejected();
        $event->getStateMachine()->apply(PaymentTransitions::TRANSITION_PROCESS, true);
    }
}
