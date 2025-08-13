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

namespace Sylius\AdyenPlugin\Provider\ExpressCheckout\GooglePay;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TransactionInfoProvider implements TransactionInfoProviderInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function provide(OrderInterface $order): array
    {
        return [
            'currencyCode' => $order->getCurrencyCode(),
            'totalPriceStatus' => self::TOTAL_PRICE_STATUS_FINAL,
            'totalPrice' => $this->formatPrice($order->getTotal()),
            'totalPriceLabel' => $this->translator->trans('sylius.ui.order_total'),
            'displayItems' => $this->getDisplayItems($order),
        ];
    }

    private function getDisplayItems(OrderInterface $order): array
    {
        return [
            [
                'label' => $this->translator->trans('sylius.ui.items_total'),
                'type' => self::DISPLAY_ITEM_TYPE_SUBTOTAL,
                'price' => $this->formatPrice($order->getItemsSubtotal()),
            ],
            [
                'label' => $this->translator->trans('sylius.ui.discount'),
                'type' => self::DISPLAY_ITEM_TYPE_DISCOUNT,
                'price' => $this->formatPrice($order->getOrderPromotionTotal()),
            ],
            [
                'label' => $this->translator->trans('sylius.ui.shipping_total'),
                'type' => self::DISPLAY_ITEM_TYPE_SHIPPING_OPTION,
                'price' => $this->formatPrice($order->getShippingTotal()),
            ],
            [
                'label' => $this->translator->trans('sylius.ui.taxes_total'),
                'type' => self::DISPLAY_ITEM_TYPE_TAX,
                'price' => $this->formatPrice($order->getTaxExcludedTotal()),
            ],
        ];
    }

    private function formatPrice(int $price): string
    {
        return number_format($price / 100, 2, '.', '');
    }
}
