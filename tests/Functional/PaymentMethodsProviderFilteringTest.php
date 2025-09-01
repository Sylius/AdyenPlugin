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

namespace Tests\Sylius\AdyenPlugin\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\AdyenPlugin\Filter\PaymentMethodsFilterInterface;
use Sylius\AdyenPlugin\Filter\PaymentMethodsMode;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProviderInterface;
use Tests\Sylius\AdyenPlugin\Functional\Stub\TestConfiguredPaymentMethodsFilter;

final class PaymentMethodsProviderFilteringTest extends AdyenTestCase
{
    protected function initializeServices($container): void
    {
        $this->setupTestCartContext();
    }

    #[DataProvider('cases')]
    public function testFilteringPipeline(
        array $availablePaymentMethods,
        array $storedPaymentMethods,
        array $allowedTypes,
        string $mode,
        array $expectedAvailable,
        array $expectedStored,
    ): void {
        $container = self::getContainer();
        $provider = $container->get(PaymentMethodsProviderInterface::class);

        /** @var TestConfiguredPaymentMethodsFilter $filter */
        $filter = $container->get(PaymentMethodsFilterInterface::class);
        $filter->setConfig($allowedTypes, PaymentMethodsMode::from($mode));

        $this->adyenClientStub->setPaymentMethodsResponse($availablePaymentMethods, $storedPaymentMethods);

        $out = $provider->provideForOrder(self::PAYMENT_METHOD_CODE, $this->testOrder);

        self::assertSame(
            $expectedAvailable,
            array_map(fn ($paymentMethod) => $paymentMethod->type, $out['paymentMethods']),
        );
        self::assertSame(
            $expectedStored,
            array_map(fn ($storedPaymentMethod) => $storedPaymentMethod->id, $out['storedPaymentMethods']),
        );
    }

    public static function cases(): array
    {
        return [
            'CONFIG: allows only paypal and filters stored accordingly' => [
                'availablePaymentMethods' => [
                    ['type' => 'scheme', 'name' => 'Cards'],
                    ['type' => 'paypal', 'name' => 'PayPal'],
                ],
                'storedPaymentMethods' => [
                    ['id' => 's1', 'type' => 'scheme', 'brand' => 'visa'],
                    ['id' => 's2', 'type' => 'paypal'],
                ],
                'allowedTypes' => ['paypal'],
                'mode' => 'config',
                'expectedAvailable' => ['paypal'],
                'expectedStored' => ['s2'],
            ],

            'MERCHANT_ACCOUNT: pass-through on available, but stored still filtered by available' => [
                'availablePaymentMethods' => [
                    ['type' => 'scheme'],
                    ['type' => 'paypal'],
                ],
                'storedPaymentMethods' => [
                    ['id' => 'a', 'type' => 'scheme'],
                    ['id' => 'b', 'type' => 'paypal'],
                    ['id' => 'c', 'type' => 'ideal'], // should be filtered out
                ],
                'allowedTypes' => ['blik'], // ignored in merchant_account mode
                'mode' => 'merchant_account',
                'expectedAvailable' => ['scheme', 'paypal'],
                'expectedStored' => ['a', 'b'],
            ],

            'CONFIG: empty allowed yields no available and no stored' => [
                'availablePaymentMethods' => [['type' => 'scheme']],
                'storedPaymentMethods' => [['id' => 'x', 'type' => 'scheme']],
                'allowedTypes' => [],
                'mode' => 'config',
                'expectedAvailable' => [],
                'expectedStored' => [],
            ],

            'CONFIG: multiple allowed preserves input order' => [
                'availablePaymentMethods' => [
                    ['type' => 'paypal'],
                    ['type' => 'scheme'],
                    ['type' => 'ideal'],
                ],
                'storedPaymentMethods' => [
                    ['id' => 'card', 'type' => 'scheme'],
                    ['id' => 'pp', 'type' => 'paypal'],
                ],
                'allowedTypes' => ['scheme', 'paypal'],
                'mode' => 'config',
                'expectedAvailable' => ['paypal', 'scheme'],
                'expectedStored' => ['card', 'pp'],
            ],

            'CONFIG: unknown allowed types produce empty result' => [
                'availablePaymentMethods' => [
                    ['type' => 'scheme'],
                    ['type' => 'paypal'],
                ],
                'storedPaymentMethods' => [
                    ['id' => 'card', 'type' => 'scheme'],
                ],
                'allowedTypes' => ['not_a_real_method'],
                'mode' => 'config',
                'expectedAvailable' => [],
                'expectedStored' => [],
            ],

            'CONFIG: allowed types are case-sensitive' => [
                'availablePaymentMethods' => [
                    ['type' => 'paypal'],
                ],
                'storedPaymentMethods' => [
                    ['id' => 'pp', 'type' => 'paypal'],
                ],
                'allowedTypes' => ['PayPal'],
                'mode' => 'config',
                'expectedAvailable' => [],
                'expectedStored' => [],
            ],

            'ANY: empty available makes stored empty as well' => [
                'availablePaymentMethods' => [],
                'storedPaymentMethods' => [
                    ['id' => 'pp', 'type' => 'paypal'],
                    ['id' => 'card', 'type' => 'scheme'],
                ],
                'allowedTypes' => ['paypal', 'scheme'],
                'mode' => 'merchant_account',
                'expectedAvailable' => [],
                'expectedStored' => [],
            ],

            'CONFIG: item without type in available is ignored' => [
                'availablePaymentMethods' => [
                    ['name' => 'Nameless'], // invalid, no type, mapper should ignore
                    ['type' => 'scheme', 'name' => 'Cards'],
                    ['type' => 'paypal', 'name' => 'PayPal'],
                ],
                'storedPaymentMethods' => [
                    ['id' => 'pp', 'type' => 'paypal'],
                ],
                'allowedTypes' => ['paypal'],
                'mode' => 'config',
                'expectedAvailable' => ['paypal'],
                'expectedStored' => ['pp'],
            ],
        ];
    }
}
