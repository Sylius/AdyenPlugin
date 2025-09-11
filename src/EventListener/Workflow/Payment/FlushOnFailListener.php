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

namespace Sylius\AdyenPlugin\EventListener\Workflow\Payment;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Event\Event;

final class FlushOnFailListener
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(Event $event): void
    {
        $this->entityManager->flush();
    }
}
