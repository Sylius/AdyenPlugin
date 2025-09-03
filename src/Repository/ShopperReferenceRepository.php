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

namespace Sylius\AdyenPlugin\Repository;

use Sylius\AdyenPlugin\Entity\ShopperReferenceInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class ShopperReferenceRepository extends EntityRepository implements ShopperReferenceRepositoryInterface
{
    public function findOneByPaymentMethodAndCustomer(
        PaymentMethodInterface $paymentMethod,
        CustomerInterface $customer,
    ): ?ShopperReferenceInterface {
        $result = $this->findOneBy([
            'paymentMethod' => $paymentMethod,
            'customer' => $customer,
        ]);

        return $result instanceof ShopperReferenceInterface ? $result : null;
    }
}
