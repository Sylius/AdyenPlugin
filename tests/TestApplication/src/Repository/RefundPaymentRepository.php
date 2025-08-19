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

namespace Tests\Sylius\AdyenPlugin\Repository;

use Sylius\AdyenPlugin\Repository\RefundPaymentRepositoryInterface;
use Sylius\AdyenPlugin\Repository\RefundPaymentRepositoryTrait;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class RefundPaymentRepository extends EntityRepository implements RefundPaymentRepositoryInterface
{
    use RefundPaymentRepositoryTrait;
}
