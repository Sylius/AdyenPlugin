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

use Sylius\AdyenPlugin\Checker\PaymentPayByLinkAvailabilityCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PaymentLinkGenerationCheckerExtension extends AbstractExtension
{
    public function __construct(
        private readonly PaymentPayByLinkAvailabilityCheckerInterface $paymentPayByLinkAvailabilityChecker,
    ) {
    }

    /** @return array<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'sylius_adyen_can_generate_payment_link',
                $this->paymentPayByLinkAvailabilityChecker->canBeGenerated(...),
                ['is_safe' => ['html']],
            ),
        ];
    }
}
