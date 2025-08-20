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

namespace Tests\Sylius\AdyenPlugin\Unit\Clearer;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Clearer\PaymentReferencesClearer;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentReferencesClearerTest extends TestCase
{
    private AdyenReferenceRepositoryInterface|MockObject $adyenReferenceRepository;

    private EntityManagerInterface|MockObject $entityManager;

    private PaymentReferencesClearer $clearer;

    protected function setUp(): void
    {
        $this->adyenReferenceRepository = $this->createMock(AdyenReferenceRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->clearer = new PaymentReferencesClearer(
            $this->adyenReferenceRepository,
            $this->entityManager,
        );
    }

    public function testClearDoesNothingWhenPaymentShouldNotBeCleared(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_PROCESSING);

        $payment->expects($this->never())
            ->method('setDetails');

        $this->adyenReferenceRepository->expects($this->never())
            ->method('findAllByPayment');

        $this->entityManager->expects($this->never())
            ->method('remove');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->clearer->clear($payment);
    }

    #[DataProvider('provideState')]
    public function testClearRemovesAllReferencesAndClearsPaymentDetails(string $state): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $reference1 = $this->createMock(AdyenReferenceInterface::class);
        $reference2 = $this->createMock(AdyenReferenceInterface::class);
        $reference3 = $this->createMock(AdyenReferenceInterface::class);

        $references = [$reference1, $reference2, $reference3];

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn($state);

        $payment->expects($this->once())
            ->method('setDetails')
            ->with([]);

        $this->adyenReferenceRepository->expects($this->once())
            ->method('findAllByPayment')
            ->with($payment)
            ->willReturn($references);

        $this->entityManager->expects($this->exactly(3))
            ->method('remove')
            ->willReturnCallback(function ($reference) use ($reference1, $reference2, $reference3) {
                $this->assertContains($reference, [$reference1, $reference2, $reference3]);
            });

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->clearer->clear($payment);
    }

    #[DataProvider('provideState')]
    public function testClearHandlesSingleReference(string $state): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $reference = $this->createMock(AdyenReferenceInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn($state);

        $payment->expects($this->once())
            ->method('setDetails')
            ->with([]);

        $this->adyenReferenceRepository->expects($this->once())
            ->method('findAllByPayment')
            ->with($payment)
            ->willReturn([$reference]);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($reference);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->clearer->clear($payment);
    }

    #[DataProvider('provideState')]
    public function testClearHandlesNoReferences(string $state): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn($state);

        $payment->expects($this->once())
            ->method('setDetails')
            ->with([]);

        $this->adyenReferenceRepository->expects($this->once())
            ->method('findAllByPayment')
            ->with($payment)
            ->willReturn([]);

        $this->entityManager->expects($this->never())
            ->method('remove');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->clearer->clear($payment);
    }

    public static function provideState(): \Generator
    {
        yield [PaymentInterface::STATE_NEW];
        yield [PaymentInterface::STATE_CART];
    }
}
