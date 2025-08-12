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

namespace Tests\Sylius\AdyenPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\AdyenPlugin\Entity\CommodityCodeAwareInterface;
use Sylius\AdyenPlugin\Entity\CommodityCodeAwareTrait;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_variant')]
class ProductVariant extends BaseProductVariant implements CommodityCodeAwareInterface
{
    use CommodityCodeAwareTrait;
}
