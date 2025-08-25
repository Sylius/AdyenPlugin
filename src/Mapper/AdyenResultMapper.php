<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Mapper;

use Sylius\AdyenPlugin\Model\PaymentOutcome;
use Sylius\Component\Core\Model\PaymentInterface;

class AdyenResultMapper
{
    public function map(array $result, PaymentInterface $payment): PaymentOutcome
    {
        $resultCode = strtolower((string) ($result['resultCode'] ?? ''));

        // Decide high-level outcome type
        $redirectRequiredCodes = [
            'redirectshopper',
            'identifshopper', // guard for typos; real code below
            'identifyshopper',
            'challengeshopper',
            'presenttoshopper',
        ];

        $type = match (true) {
            isset($result['action']) => 'redirect_required',
            in_array($resultCode, $redirectRequiredCodes, true) => 'redirect_required',
            $resultCode === 'authorised' => 'authorised',
            in_array($resultCode, ['received', 'processing'], true) => 'pending',
            in_array($resultCode, ['refused', 'rejected', 'cancelled', 'error'], true) => 'failed',
            default => 'failed',
        };

        $order = $payment->getOrder();
        $orderLocale = method_exists($order, 'getLocaleCode') ? $order->getLocaleCode() : null;
        $tokenValue = method_exists($order, 'getTokenValue') ? $order->getTokenValue() : null;
        $pspReference = $result['pspReference'] ?? null;

        // Construct domain outcome (constructor expected to accept these fields)
        return new PaymentOutcome(
            type: PaymentOutcome::fromString($type),
            paymentId: (string) $payment->getId(),
            pspReference: is_string($pspReference) ? $pspReference : null,
            orderLocale: is_string($orderLocale) ? $orderLocale : null,
            tokenValue: is_string($tokenValue) ? $tokenValue : null,
        );
    }
}
