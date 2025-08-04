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

namespace Sylius\AdyenPlugin\Client;

use Webmozart\Assert\Assert;

final class PaymentMethodsFilter implements PaymentMethodsFilterInterface
{
    /** @var array|null */
    private $supportedMethodsList;

    public function __construct(?array $supportedMethodsList)
    {
        $this->supportedMethodsList = $supportedMethodsList;
    }

    private function doFilter(array $methodsList): array
    {
        $result = array_filter($methodsList, function (array $item): bool {
            Assert::keyExists($item, 'type');

            return in_array($item['type'], (array) $this->supportedMethodsList, true);
        }, \ARRAY_FILTER_USE_BOTH);

        return array_values($result);
    }

    public function filter(array $paymentMethodsResponse): array
    {
        Assert::keyExists($paymentMethodsResponse, 'paymentMethods');

        if (0 < count((array) $this->supportedMethodsList)) {
            $paymentMethodsResponse['paymentMethods'] = $this->doFilter(
                (array) $paymentMethodsResponse['paymentMethods'],
            );
        }

        return $paymentMethodsResponse;
    }
}
