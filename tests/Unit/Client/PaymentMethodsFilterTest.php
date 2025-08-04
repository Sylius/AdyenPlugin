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

namespace Tests\Sylius\AdyenPlugin\Unit\Client;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Client\PaymentMethodsFilter;
use Sylius\AdyenPlugin\Client\PaymentMethodsFilterInterface;

class PaymentMethodsFilterTest extends TestCase
{
    private function getFilter(?array $supportedMethodsList): PaymentMethodsFilterInterface
    {
        return new PaymentMethodsFilter($supportedMethodsList);
    }

    public static function provideForTestFilter(): array
    {
        return [
            'empty supported methods list' => [
                [
                    ['type' => 'first'],
                    ['type' => 'second'],
                ],
                null,
                [
                    ['type' => 'first'],
                    ['type' => 'second'],
                ],
            ],
            'non-empty supported list' => [
                [
                    ['type' => 'first'],
                    ['type' => 'second'],
                    ['type' => 'third'],
                ],
                ['first', 'third'],
                [
                    ['type' => 'first'],
                    ['type' => 'third'],
                ],
            ],
        ];
    }

    #[DataProvider('provideForTestFilter')]
    public function testFilter(
        array $paymentMethodsResponseList,
        ?array $supportedMethodsList,
        array $expected,
    ): void {
        $response = [
            'paymentMethods' => $paymentMethodsResponseList,
        ];

        $filter = $this->getFilter($supportedMethodsList);
        $result = $filter->filter($response);

        $expected = [
            'paymentMethods' => $expected,
        ];

        $this->assertEquals($expected, $result);
    }
}
