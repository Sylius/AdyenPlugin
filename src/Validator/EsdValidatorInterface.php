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

namespace Sylius\AdyenPlugin\Validator;

interface EsdValidatorInterface
{
    public function validateLevel2Data(array $esd): array;

    public function validateLevel3Data(array $esd): array;

    public function validateAirlineData(array $esd): array;

    public function validateLodgingData(array $esd): array;

    public function validateCarRentalData(array $esd): array;
}
