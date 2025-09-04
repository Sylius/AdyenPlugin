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

namespace Sylius\AdyenPlugin\Twig\Extension;

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\Component\Core\Model\PaymentInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdyenPaymentCheckerExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
    ) {
    }

    /** @return array<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'sylius_adyen_is_adyen_payment',
                $this->adyenPaymentMethodChecker->isAdyenPayment(...),
                ['is_safe' => ['html']],
            ),
            new TwigFunction(
                'sylius_adyen_can_be_captured',
                [$this, 'canBeCaptured'],
                ['is_safe' => ['html']],
            ),
        ];
    }

    public function canBeCaptured(PaymentInterface $payment): bool
    {
        return
            $this->adyenPaymentMethodChecker->isAdyenPayment($payment) &&
            $this->adyenPaymentMethodChecker->isCaptureMode($payment, PaymentCaptureMode::MANUAL) &&
            $payment->getState() === PaymentInterface::STATE_AUTHORIZED
        ;
    }
}
