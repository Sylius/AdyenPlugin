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

namespace Sylius\AdyenPlugin\Decider;

class PaymentRedirectDecider implements PaymentRedirectDeciderInterface
{
    public function shouldRedirect(array $paymentCreationResult): bool
    {
        // Adyen signals client-side continuation primarily via the presence of `action`.
        if (isset($paymentCreationResult['action'])) {
            return true;
        }

        // Fallback based on resultCode for older/edge flows.
        $resultCode = strtolower((string) ($paymentCreationResult['resultCode'] ?? ''));

        // Known resultCodes that require client (browser) continuation.
        // See Adyen: RedirectShopper / IdentifyShopper / ChallengeShopper / PresentToShopper.
        // Do NOT treat 'pending' / 'received' as redirect-required – those are server-side/processing states.
        $clientContinuationCodes = [
            'redirectshopper',
            'identifyshopper',
            'challengeshopper',
            'presenttoshopper',
        ];

        return in_array($resultCode, $clientContinuationCodes, true);
    }
}
