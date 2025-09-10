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
use Sylius\AdyenPlugin\Filter\ManualCapturePaymentMethodsFilter;
use Sylius\AdyenPlugin\Model\PaymentMethod;

final class ManualCapturePaymentMethodsFilterTest extends TestCase
{
    public function testItReturnsAllPaymentMethodsWhenManualCaptureIsDisabled(): void
    {
        $paymentMethodsFilter = new ManualCapturePaymentMethodsFilter(['scheme', 'ideal']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');
        $paypal = new PaymentMethod('paypal', 'PayPal');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal, $paypal], ['manual_capture' => false]);

        self::assertSame([$scheme, $ideal, $paypal], $result);
    }

    public function testItReturnsAllPaymentMethodsWhenManualCaptureContextIsNotProvided(): void
    {
        $paymentMethodsFilter = new ManualCapturePaymentMethodsFilter(['scheme', 'ideal']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');
        $paypal = new PaymentMethod('paypal', 'PayPal');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal, $paypal]);

        self::assertSame([$scheme, $ideal, $paypal], $result);
    }

    public function testItFiltersOnlyManualCaptureSupportingTypesWhenManualCaptureIsEnabled(): void
    {
        $paymentMethodsFilter = new ManualCapturePaymentMethodsFilter(['scheme', 'ideal']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');
        $paypal = new PaymentMethod('paypal', 'PayPal');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal, $paypal], ['manual_capture' => true]);

        self::assertSame([$scheme, $ideal], $result);
    }

    public function testItReturnsEmptyArrayWhenNoMethodsSupportManualCapture(): void
    {
        $paymentMethodsFilter = new ManualCapturePaymentMethodsFilter([]);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal], ['manual_capture' => true]);

        self::assertSame([], $result);
    }

    public function testItReturnsEmptyArrayWhenManualCaptureIsEnabledAndNoMatchingMethods(): void
    {
        $paymentMethodsFilter = new ManualCapturePaymentMethodsFilter(['klarna']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal], ['manual_capture' => true]);

        self::assertSame([], $result);
    }
}
