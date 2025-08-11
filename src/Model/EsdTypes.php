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

namespace Sylius\AdyenPlugin\Model;

final class EsdTypes
{
    public const TYPE_LEVEL2 = 'level2';

    public const TYPE_LEVEL3 = 'level3';

    public const TYPE_AIRLINE = 'airline';

    public const TYPE_LODGING = 'lodging';

    public const TYPE_CAR_RENTAL = 'car_rental';

    public const TYPE_TEMPORARY_SERVICES = 'temporary_services';
}
