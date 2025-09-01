<?php

declare(strict_types=1);

namespace Tests\Sylius\AdyenPlugin\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Filter\ConfiguredPaymentMethodsFilter;
use Sylius\AdyenPlugin\Model\PaymentMethod;

final class ConfiguredPaymentMethodsFilterTest extends TestCase
{
    public function testItReturnsAllPaymentMethodsWhenAllowedTypesIsEmpty(): void
    {
        $paymentMethodsFilter = new ConfiguredPaymentMethodsFilter([]);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal]);

        self::assertSame([$scheme, $ideal], $result);
    }

    public function testItFiltersOnlyAllowedTypes(): void
    {
        $paymentMethodsFilter = new ConfiguredPaymentMethodsFilter(['scheme']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal]);

        self::assertSame([$scheme], $result);
    }

    public function testItReturnsEmptyArrayWhenNoMethodsAreAllowed(): void
    {
        $paymentMethodsFilter = new ConfiguredPaymentMethodsFilter(['paypal']);

        $scheme = new PaymentMethod('scheme', 'Card');
        $ideal = new PaymentMethod('ideal', 'iDEAL');

        $result = $paymentMethodsFilter->filter([$scheme, $ideal]);

        self::assertSame([], $result);
    }
}
