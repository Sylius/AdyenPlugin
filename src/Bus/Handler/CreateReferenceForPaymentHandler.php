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

use Doctrine\ORM\NoResultException;
use Sylius\AdyenPlugin\Bus\Command\CreateReferenceForPayment;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Factory\AdyenReferenceFactoryInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final class CreateReferenceForPaymentHandler
{
    public function __construct(
        private readonly AdyenReferenceRepositoryInterface $adyenReferenceRepository,
        private readonly AdyenReferenceFactoryInterface $adyenReferenceFactory,
    ) {
    }

    public function __invoke(CreateReferenceForPayment $referenceCommand): void
    {
        $object = $this->adyenReferenceFactory->createForPayment($referenceCommand->getPayment());
        $existing = $this->getExisting($object);

        if (null !== $existing) {
            $existing->touch();
            $object = $existing;
        }

        $this->adyenReferenceRepository->add($object);
    }

    private function getExisting(AdyenReferenceInterface $adyenReference): ?AdyenReferenceInterface
    {
        $payment = $adyenReference->getPayment();
        Assert::notNull($payment);

        $method = $payment->getMethod();
        Assert::notNull($method);

        $code = (string) $method->getCode();

        try {
            return $this->adyenReferenceRepository->getOneByCodeAndReference(
                $code,
                (string) $adyenReference->getPspReference(),
            );
        } catch (NoResultException) {
            return null;
        }
    }
}
