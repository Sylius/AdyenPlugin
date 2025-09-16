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

namespace Tests\Sylius\AdyenPlugin\Unit\EventListener\Workflow\Payment;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\FlushOnFailListener;
use Symfony\Component\Workflow\Event\Event;

final class FlushOnFailListenerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;

    private FlushOnFailListener $listener;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->listener = new FlushOnFailListener($this->entityManager);
    }

    public function testItFlushesEntityManager(): void
    {
        $event = $this->createMock(Event::class);

        $this->entityManager->expects($this->once())->method('flush');

        ($this->listener)($event);
    }

    public function testItFlushesRegardlessOfEventSubject(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->never())->method('getSubject');
        $this->entityManager->expects($this->once())->method('flush');

        ($this->listener)($event);
    }
}
