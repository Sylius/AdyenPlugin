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
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Exception\UnprocessablePaymentException;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class RoutePaymentCompleteTransitionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SMEvents::PRE_TRANSITION => 'doFilter',
            SMEvents::TEST_TRANSITION => 'canComplete',
        ];
    }

    public function canComplete(TransitionEvent $event): void
    {
        if (
            !$this->isProcessableAdyenPayment($event) ||
            PaymentInterface::STATE_PROCESSING !== $event->getState() ||
            PaymentGraph::TRANSITION_CAPTURE === $event->getTransition()
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

        $payment = $this->getObject($event);
        if (
            !$this->adyenPaymentMethodChecker->isAdyenPayment($payment) ||
            !$this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::AUTOMATIC)
        ) {
            return;
        }

        $order = $this->getObject($event)->getOrder();
        $this->messageBus->dispatch(new RequestCapture($order));

        $event->setRejected();
        $event->getStateMachine()->apply(PaymentGraph::TRANSITION_PROCESS, true);
    }

    private function isProcessableAdyenPayment(TransitionEvent $event): bool
    {
        if (PaymentGraph::GRAPH !== $event->getStateMachine()->getGraph()) {
            return false;
        }

        if (PaymentGraph::TRANSITION_COMPLETE !== $event->getTransition()) {
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
}
