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

use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class AdyenTokenRepository extends EntityRepository implements AdyenTokenRepositoryInterface
{
    public function findOneByPaymentMethodAndCustomer(
        PaymentMethodInterface $paymentMethod,
        CustomerInterface $customer,
    ): ?AdyenTokenInterface {
        $result = $this->findOneBy([
            'paymentMethod' => $paymentMethod,
            'customer' => $customer,
        ]);

        return $result instanceof AdyenTokenInterface ? $result : null;
    }
}
