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

use Sylius\AdyenPlugin\Bus\Command\CreateReferenceForRefund;
use Sylius\AdyenPlugin\Factory\AdyenReferenceFactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateReferenceForRefundHandler
{
    /** @var RepositoryInterface */
    private $adyenReferenceRepository;

    /** @var AdyenReferenceFactoryInterface */
    private $adyenReferenceFactory;

    public function __construct(
        RepositoryInterface $adyenReferenceRepository,
        AdyenReferenceFactoryInterface $adyenReferenceFactory,
    ) {
        $this->adyenReferenceRepository = $adyenReferenceRepository;
        $this->adyenReferenceFactory = $adyenReferenceFactory;
    }

    public function __invoke(CreateReferenceForRefund $referenceCommand): void
    {
        $object = $this->adyenReferenceFactory->createForRefund(
            $referenceCommand->getRefundReference(),
            $referenceCommand->getPayment(),
            $referenceCommand->getRefundPayment(),
        );
        $this->adyenReferenceRepository->add($object);
    }
}
