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

namespace Sylius\AdyenPlugin\Normalizer;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ShippingLineGenerator implements ShippingLineGeneratorInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function generate(array $items, OrderInterface $order): array
    {
        $netSum = array_sum(array_column($items, 'amountExcludingTax'));
        $totalSum = array_sum(array_column($items, 'amountIncludingTax'));

        return [
            'amountExcludingTax' => $order->getTotal() - $order->getTaxTotal() - $netSum,
            'amountIncludingTax' => $order->getTotal() - $totalSum,
            'description' => $this->translator->trans('sylius.ui.shipping'),
            'quantity' => 1,
        ];
    }
}
