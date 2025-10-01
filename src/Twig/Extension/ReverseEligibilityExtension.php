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

use Sylius\AdyenPlugin\Checker\ReverseEligibilityCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ReverseEligibilityExtension extends AbstractExtension
{
    public function __construct(
        private readonly ReverseEligibilityCheckerInterface $reverseEligibilityChecker,
    ) {
    }

    /** @return array<TwigFunction> */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'sylius_adyen_can_reverse',
                [$this, 'canReverse'],
                ['is_safe' => ['html']],
            ),
        ];
    }

    public function canReverse(OrderInterface $order): bool
    {
        return $this->reverseEligibilityChecker->canReverse($order);
    }
}
