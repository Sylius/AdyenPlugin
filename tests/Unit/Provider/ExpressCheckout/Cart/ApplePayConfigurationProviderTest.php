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

namespace Tests\Sylius\AdyenPlugin\Unit\Provider\ExpressCheckout\Cart;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\Cart\ApplePayConfigurationProvider;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ApplePayConfigurationProviderTest extends TestCase
{
    private MockObject&UrlGeneratorInterface $urlGenerator;

    private ApplePayConfigurationProvider $applePayConfigurationProvider;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->applePayConfigurationProvider = new ApplePayConfigurationProvider($this->urlGenerator);
    }

    public function testGetConfigurationReturnsCorrectStructure(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTotal')->willReturn(12500);
        $order->method('getCurrencyCode')->willReturn('USD');

        $this->urlGenerator->expects($this->exactly(4))
            ->method('generate')
            ->willReturnCallback(function (string $route) {
                return match ($route) {
                    'sylius_adyen_shop_express_checkout_apple_pay_shipping_address_change' => '/shop/express-checkout/apple-pay/address-change',
                    'sylius_adyen_shop_express_checkout_apple_pay_shipping_options_change' => '/shop/express-checkout/apple-pay/options-change',
                    'sylius_adyen_shop_express_checkout_apple_pay_checkout' => '/shop/express-checkout/apple-pay/checkout',
                    'sylius_adyen_shop_payments' => '/shop/payments',
                    default => throw new \InvalidArgumentException("Unexpected route: $route"),
                };
            });

        $result = $this->applePayConfigurationProvider->getConfiguration($order);

        $expected = [
            'amount' => [
                'value' => 12500,
                'currency' => 'USD',
            ],
            'path' => [
                'addressChange' => '/shop/express-checkout/apple-pay/address-change',
                'optionsChange' => '/shop/express-checkout/apple-pay/options-change',
                'checkout' => '/shop/express-checkout/apple-pay/checkout',
                'payments' => '/shop/payments',
            ],
        ];

        $this->assertSame($expected, $result);
    }
}
