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

namespace Sylius\AdyenPlugin\Resolver;

use Sylius\AdyenPlugin\Entity\ShopperReferenceInterface;
use Sylius\AdyenPlugin\Factory\ShopperReferenceFactoryInterface;
use Sylius\AdyenPlugin\Repository\ShopperReferenceRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class ShopperReferenceResolver implements ShopperReferenceResolverInterface
{
    public function __construct(
        private readonly ShopperReferenceRepositoryInterface $shopperReferenceRepository,
        private readonly ShopperReferenceFactoryInterface $shopperReferenceFactory,
    ) {
    }

    public function resolve(PaymentMethodInterface $paymentMethod, CustomerInterface $customer): ShopperReferenceInterface
    {
        $shopperReference = $this->shopperReferenceRepository->findOneByPaymentMethodAndCustomer($paymentMethod, $customer);

        if ($shopperReference === null) {
            $shopperReference = $this->shopperReferenceFactory->create($paymentMethod, $customer);
            $this->shopperReferenceRepository->add($shopperReference);
        }

        return $shopperReference;
    }
}
