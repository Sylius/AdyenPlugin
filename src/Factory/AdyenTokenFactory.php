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

namespace Sylius\AdyenPlugin\Factory;

use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class AdyenTokenFactory implements AdyenTokenFactoryInterface
{
    /** @var FactoryInterface */
    private $baseFactory;

    public function __construct(FactoryInterface $baseFactory)
    {
        $this->baseFactory = $baseFactory;
    }

    public function create(PaymentMethodInterface $paymentMethod, CustomerInterface $customer): AdyenTokenInterface
    {
        $result = $this->createNew();
        $result->setIdentifier(
            bin2hex(random_bytes(32)),
        );
        $result->setCustomer($customer);
        $result->setPaymentMethod($paymentMethod);

        return $result;
    }

    public function createNew(): AdyenTokenInterface
    {
        /**
         * @var AdyenTokenInterface $result
         */
        $result = $this->baseFactory->createNew();

        return $result;
    }
}
