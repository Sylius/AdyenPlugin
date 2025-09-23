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

namespace Sylius\AdyenPlugin\Provider\ExpressCheckout\ApplePay;

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
            'newTotal' => [
                'label' => $this->translator->trans('sylius.ui.order_total'),
                'amount' => $this->formatPrice($order->getTotal()),
                'type' => self::TOTAL_PRICE_STATUS_FINAL,
            ],
            'newLineItems' => $this->getLineItems($order),
        ];
    }

    private function getLineItems(OrderInterface $order): array
    {
        return [
            [
                'label' => $this->translator->trans('sylius.ui.items_total'),
                'amount' => $this->formatPrice($order->getItemsSubtotal()),
                'type' => self::TOTAL_PRICE_STATUS_FINAL,
            ],
            [
                'label' => $this->translator->trans('sylius.ui.discount'),
                'amount' => $this->formatPrice($order->getOrderPromotionTotal()),
                'type' => self::TOTAL_PRICE_STATUS_FINAL,
            ],
            [
                'label' => $this->translator->trans('sylius.ui.shipping_total'),
                'amount' => $this->formatPrice($order->getShippingTotal()),
                'type' => self::TOTAL_PRICE_STATUS_FINAL,
            ],
            [
                'label' => $this->translator->trans('sylius.ui.taxes_total'),
                'amount' => $this->formatPrice($order->getTaxExcludedTotal()),
                'type' => self::TOTAL_PRICE_STATUS_FINAL,
            ],
        ];
    }

    private function formatPrice(int $price): string
    {
        return number_format($price / 100, 2, '.', '');
    }
}
