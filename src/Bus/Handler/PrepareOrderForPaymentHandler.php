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

namespace Sylius\AdyenPlugin\Bus\Handler;

use Sylius\AdyenPlugin\Bus\Command\PrepareOrderForPayment;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PrepareOrderForPaymentHandler
{
    /** @var OrderNumberAssignerInterface */
    private $orderNumberAssigner;

    /** @var RepositoryInterface */
    private $orderRepository;

    public function __construct(
        OrderNumberAssignerInterface $orderNumberAssigner,
        RepositoryInterface $orderRepository,
    ) {
        $this->orderNumberAssigner = $orderNumberAssigner;
        $this->orderRepository = $orderRepository;
    }

    public function __invoke(PrepareOrderForPayment $command): void
    {
        $this->orderNumberAssigner->assignNumber($command->getOrder());
        $this->orderRepository->add($command->getOrder());
    }
}
