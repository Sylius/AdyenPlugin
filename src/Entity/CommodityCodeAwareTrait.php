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

namespace Sylius\AdyenPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

trait CommodityCodeAwareTrait
{
    #[ORM\Column(name: 'commodity_code', type: 'string', length: 12, nullable: true)]
    protected ?string $commodityCode = null;

    public function getCommodityCode(): ?string
    {
        return $this->commodityCode;
    }

    public function setCommodityCode(?string $commodityCode): void
    {
        $this->commodityCode = $commodityCode;
    }
}
