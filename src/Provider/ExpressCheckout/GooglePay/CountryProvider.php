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

use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class CountryProvider implements CountryProviderInterface
{
    public function __construct(
        private readonly RepositoryInterface $countryRepository,
    ) {
    }

    /**
     * @return string[]
     */
    public function getAllowedCountryCodes(ChannelInterface $channel): array
    {
        $countries = $channel->getCountries();
        $countries = false === $countries->isEmpty() ? $countries->toArray() : $this->countryRepository->findBy(['enabled' => true]);

        return array_map(static fn (CountryInterface $country): string => $country->getCode(), $countries);
    }
}
