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

namespace Sylius\AdyenPlugin\Checker\Eligibility;

use Sylius\Component\Addressing\Matcher\ZoneMatcherInterface;
use Sylius\Component\Core\Model\Scope;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Checker\Eligibility\ShippingMethodEligibilityCheckerInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface as BaseShippingMethodInterface;
use Sylius\Component\Shipping\Model\ShippingSubjectInterface;
use Webmozart\Assert\Assert;

final class ZoneShippingMethodEligibilityChecker implements ShippingMethodEligibilityCheckerInterface
{
    public function __construct(
        private readonly ZoneMatcherInterface $zoneMatcher,
    ) {
    }

    public function isEligible(ShippingSubjectInterface $shippingSubject, BaseShippingMethodInterface $shippingMethod): bool
    {
        Assert::isInstanceOf($shippingSubject, ShipmentInterface::class);
        Assert::isInstanceOf($shippingMethod, ShippingMethodInterface::class);

        $shippingAddress = $shippingSubject->getOrder()?->getShippingAddress();
        if (null === $shippingAddress) {
            return true;
        }

        $zones = $this->zoneMatcher->matchAll($shippingAddress, Scope::SHIPPING);
        $shippingMethodZone = $shippingMethod->getZone();

        foreach ($zones as $zone) {
            if ($zone->getCode() === $shippingMethodZone->getCode()) {
                return true;
            }
        }

        return false;
    }
}
