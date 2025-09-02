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

namespace Sylius\AdyenPlugin\Processor\Payment;

use Sylius\AdyenPlugin\Bus\Command\RequestCapture;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Exception\PaymentActionException;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class ManualCaptureProcessor implements ManualCaptureProcessorInterface
{
    public function __construct(
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function process(PaymentInterface $payment): void
    {
        if (!$this->adyenPaymentMethodChecker->isAdyenPayment($payment)) {
            throw PaymentActionException::create(sprintf('Cannot capture non Adyen payment (ID: %s)', $payment->getId()));
        }
        if ($this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::AUTOMATIC)) {
            throw PaymentActionException::create(sprintf(
                'Cannot manually capture payment (ID: %s) with automatic capture mode',
                $payment->getId(),
            ));
        }
        if ($payment->getState() !== PaymentInterface::STATE_AUTHORIZED) {
            throw PaymentActionException::create(sprintf(
                'Cannot capture payment (ID: %s) that is not in authorized state, current state: %s',
                $payment->getId(),
                $payment->getState(),
            ));
        }

        $this->messageBus->dispatch(new RequestCapture($payment->getOrder()));
    }
}
