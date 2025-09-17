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

namespace Tests\Sylius\AdyenPlugin\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Filter\GuestPaymentMethodsFilter;
use Sylius\AdyenPlugin\Model\PaymentMethod;

final class GuestPaymentMethodsFilterTest extends TestCase
{
    public function testItReturnsAllPaymentMethodsWhenUserIsNotGuest(): void
    {
        $paymentMethodsFilter = new GuestPaymentMethodsFilter(['scheme', 'ideal']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');
        $paypal = new PaymentMethod('paypal', 'PayPal');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal, $paypal], ['guest' => false]);

        self::assertSame([$scheme, $ideal, $paypal], $result);
    }

    public function testItFiltersOutOnlyLoggedInAllowedTypesWhenGuestContextIsNotProvided(): void
    {
        $paymentMethodsFilter = new GuestPaymentMethodsFilter(['scheme', 'ideal']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');
        $paypal = new PaymentMethod('paypal', 'PayPal');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal, $paypal]);

        self::assertSame([$paypal], $result);
    }

    public function testItFiltersOutOnlyLoggedInAllowedTypesForGuestUsers(): void
    {
        $paymentMethodsFilter = new GuestPaymentMethodsFilter(['scheme', 'ideal']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');
        $paypal = new PaymentMethod('paypal', 'PayPal');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal, $paypal], ['guest' => true]);

        self::assertSame([$paypal], $result);
    }

    public function testItReturnsAllPaymentMethodsWhenNoRestrictedTypesConfigured(): void
    {
        $paymentMethodsFilter = new GuestPaymentMethodsFilter([]);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal], ['guest' => true]);

        self::assertSame([$scheme, $ideal], $result);
    }

    public function testItReturnsEmptyArrayWhenAllMethodsAreRestrictedForGuests(): void
    {
        $paymentMethodsFilter = new GuestPaymentMethodsFilter(['scheme', 'ideal', 'paypal']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');
        $paypal = new PaymentMethod('paypal', 'PayPal');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal, $paypal], ['guest' => true]);

        self::assertSame([], $result);
    }

    public function testItPreservesIndexingAfterFiltering(): void
    {
        $paymentMethodsFilter = new GuestPaymentMethodsFilter(['scheme']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');
        $paypal = new PaymentMethod('paypal', 'PayPal');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal, $paypal], ['guest' => true]);

        self::assertSame([0 => $ideal, 1 => $paypal], array_values($result));
    }
}
