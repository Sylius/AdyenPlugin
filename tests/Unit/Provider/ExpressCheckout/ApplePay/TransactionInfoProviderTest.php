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

namespace Tests\Sylius\AdyenPlugin\Unit\Provider\ExpressCheckout\ApplePay;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\TransactionInfoProvider;
use Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay\TransactionInfoProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TransactionInfoProviderTest extends TestCase
{
    private MockObject&TranslatorInterface $translator;

    private TransactionInfoProvider $transactionInfoProvider;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->transactionInfoProvider = new TransactionInfoProvider($this->translator);
    }

    public function testProvideReturnsCorrectTransactionInfo(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getTotal')->willReturn(15099);
        $order->method('getItemsSubtotal')->willReturn(10000);
        $order->method('getOrderPromotionTotal')->willReturn(-1000);
        $order->method('getShippingTotal')->willReturn(500);
        $order->method('getTaxExcludedTotal')->willReturn(5599);

        $this->translator->method('trans')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'sylius.ui.order_total' => 'Order Total',
                    'sylius.ui.items_total' => 'Items Total',
                    'sylius.ui.discount' => 'Discount',
                    'sylius.ui.shipping_total' => 'Shipping Total',
                    'sylius.ui.taxes_total' => 'Taxes Total',
                    default => $key,
                };
            });

        $result = $this->transactionInfoProvider->provide($order);

        $expected = [
            'newTotal' => [
                'label' => 'Order Total',
                'amount' => '150.99',
                'type' => TransactionInfoProviderInterface::TOTAL_PRICE_STATUS_FINAL,
            ],
            'newLineItems' => [
                [
                    'label' => 'Items Total',
                    'amount' => '100.00',
                    'type' => TransactionInfoProviderInterface::TOTAL_PRICE_STATUS_FINAL,
                ],
                [
                    'label' => 'Discount',
                    'amount' => '-10.00',
                    'type' => TransactionInfoProviderInterface::TOTAL_PRICE_STATUS_FINAL,
                ],
                [
                    'label' => 'Shipping Total',
                    'amount' => '5.00',
                    'type' => TransactionInfoProviderInterface::TOTAL_PRICE_STATUS_FINAL,
                ],
                [
                    'label' => 'Taxes Total',
                    'amount' => '55.99',
                    'type' => TransactionInfoProviderInterface::TOTAL_PRICE_STATUS_FINAL,
                ],
            ],
        ];

        $this->assertSame($expected, $result);
    }
}
