<?php

declare(strict_types=1);

namespace Tests\Sylius\AdyenPlugin\Unit\Filter;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Filter\StoredPaymentMethodsFilter;
use Sylius\AdyenPlugin\Model\PaymentMethod;
use Sylius\AdyenPlugin\Model\StoredPaymentMethod;

final class StoredPaymentMethodsFilterTest extends TestCase
{
    public function testItKeepsOnlyStoredMethodsThatExistInAvailable(): void
    {
        $filter = new StoredPaymentMethodsFilter();

        $availablePaymentMethods = [
            new PaymentMethod('scheme', 'Cards'),
            new PaymentMethod('ideal', 'iDEAL'),
        ];

        $storedPaymentMethodCardVisa = new StoredPaymentMethod('tok_1', 'scheme', brand: 'visa', lastFour: '4242');
        $storedPaymentMethodPaypal = new StoredPaymentMethod('tok_2', 'paypal');
        $storedPaymentMethods = [$storedPaymentMethodCardVisa, $storedPaymentMethodPaypal];

        $result = $filter->filterAgainstAvailable($storedPaymentMethods, $availablePaymentMethods);

        self::assertSame([$storedPaymentMethodCardVisa], $result);
    }

    public function testItReturnsEmptyArrayWhenAvailableIsEmpty(): void
    {
        $filter = new StoredPaymentMethodsFilter();

        $availablePaymentMethods = [];

        $storedPaymentMethods = [
            new StoredPaymentMethod('tok_1', 'scheme'),
            new StoredPaymentMethod('tok_2', 'ideal'),
        ];

        $result = $filter->filterAgainstAvailable($storedPaymentMethods, $availablePaymentMethods);

        self::assertSame([], $result);
    }

    public function testItReturnsEmptyArrayWhenStoredIsEmpty(): void
    {
        $filter = new StoredPaymentMethodsFilter();

        $availablePaymentMethods = [
            new PaymentMethod('scheme', 'Cards'),
            new PaymentMethod('ideal', 'iDEAL'),
        ];

        $storedPaymentMethods = [];

        $result = $filter->filterAgainstAvailable($storedPaymentMethods, $availablePaymentMethods);

        self::assertSame([], $result);
    }

    public function testItKeepsAllAndReindexesOrder(): void
    {
        $filter = new StoredPaymentMethodsFilter();

        $availablePaymentMethods = [
            new PaymentMethod('scheme', 'Cards'),
            new PaymentMethod('ideal', 'iDEAL'),
        ];

        $storedPaymentMethodFirst = new StoredPaymentMethod('tok_a', 'scheme');
        $storedPaymentMethodSecond = new StoredPaymentMethod('tok_b', 'ideal');

        $storedPaymentMethods = [
            10 => $storedPaymentMethodFirst,
            20 => $storedPaymentMethodSecond,
        ];

        $result = $filter->filterAgainstAvailable($storedPaymentMethods, $availablePaymentMethods);

        self::assertSame([$storedPaymentMethodFirst, $storedPaymentMethodSecond], $result);
        self::assertSame([0, 1], array_keys($result));
    }

    public function testItFiltersOutUnknownTypes(): void
    {
        $filter = new StoredPaymentMethodsFilter();

        $availablePaymentMethods = [
            new PaymentMethod('scheme', 'Cards'),
        ];

        $storedPaymentMethods = [
            new StoredPaymentMethod('tok_a', 'unknown_type'),
            new StoredPaymentMethod('tok_b', 'scheme'),
        ];

        $result = $filter->filterAgainstAvailable($storedPaymentMethods, $availablePaymentMethods);

        self::assertCount(1, $result);
        self::assertSame('tok_b', $result[0]->id);
        self::assertSame('scheme', $result[0]->type);
    }
}
