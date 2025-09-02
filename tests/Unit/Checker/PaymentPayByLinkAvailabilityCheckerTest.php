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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Checker\PaymentPayByLinkAvailabilityChecker;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentPayByLinkAvailabilityCheckerTest extends TestCase
{
    private MockObject|PaymentLinkRepositoryInterface $paymentLinkRepository;

    private AdyenReferenceRepositoryInterface|MockObject $referenceRepository;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private PaymentPayByLinkAvailabilityChecker $checker;

    protected function setUp(): void
    {
        $this->paymentLinkRepository = $this->createMock(PaymentLinkRepositoryInterface::class);
        $this->referenceRepository = $this->createMock(AdyenReferenceRepositoryInterface::class);
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);

        $this->checker = new PaymentPayByLinkAvailabilityChecker(
            $this->paymentLinkRepository,
            $this->referenceRepository,
            $this->adyenPaymentMethodChecker,
        );
    }

    public function testCanBeGeneratedReturnsFalseWhenPaymentIsNotAdyenPayment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(false);

        $result = $this->checker->canBeGenerated($payment);

        self::assertFalse($result);
    }

    #[DataProvider('provideNotAllowedStates')]
    public function testCanBeGeneratedReturnsFalseWhenPaymentStateIsNotAllowed(string $state): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn($state);

        $this->paymentLinkRepository
            ->expects(self::never())
            ->method('findBy');

        $result = $this->checker->canBeGenerated($payment);

        self::assertFalse($result);
    }

    #[DataProvider('provideAllowedStates')]
    public function testCanBeGeneratedReturnsTrueWhenPaymentLinkExists(string $state): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn($state);

        $this->paymentLinkRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['payment' => $payment], null, 1)
            ->willReturn(['paymentLink']);

        $this->referenceRepository
            ->expects(self::never())
            ->method('findBy');

        $result = $this->checker->canBeGenerated($payment);

        self::assertTrue($result);
    }

    #[DataProvider('provideAllowedStates')]
    public function testCanBeGeneratedReturnsTrueWhenNoPaymentLinkExistsAndNoReferenceExists(string $state): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn($state);

        $this->paymentLinkRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['payment' => $payment], null, 1)
            ->willReturn([]);

        $this->referenceRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['payment' => $payment], null, 1)
            ->willReturn([]);

        $result = $this->checker->canBeGenerated($payment);

        self::assertTrue($result);
    }

    #[DataProvider('provideAllowedStates')]
    public function testCanBeGeneratedReturnsFalseWhenNoPaymentLinkExistsButReferenceExists(string $state): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn($state);

        $this->paymentLinkRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['payment' => $payment], null, 1)
            ->willReturn([]);

        $this->referenceRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['payment' => $payment], null, 1)
            ->willReturn(['reference']);

        $result = $this->checker->canBeGenerated($payment);

        self::assertFalse($result);
    }

    public function testCanBeGeneratedWithCustomAllowedStates(): void
    {
        $customAllowedStates = [PaymentInterface::STATE_COMPLETED];
        $checker = new PaymentPayByLinkAvailabilityChecker(
            $this->paymentLinkRepository,
            $this->referenceRepository,
            $this->adyenPaymentMethodChecker,
            $customAllowedStates,
        );

        $payment = $this->createMock(PaymentInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $payment
            ->expects(self::once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);

        $result = $checker->canBeGenerated($payment);

        self::assertFalse($result);
    }

    public static function provideAllowedStates(): array
    {
        return [
            'new state' => [PaymentInterface::STATE_NEW],
            'processing state' => [PaymentInterface::STATE_PROCESSING],
        ];
    }

    public static function provideNotAllowedStates(): array
    {
        return [
            'completed state' => [PaymentInterface::STATE_COMPLETED],
            'failed state' => [PaymentInterface::STATE_FAILED],
            'cancelled state' => [PaymentInterface::STATE_CANCELLED],
            'authorized state' => [PaymentInterface::STATE_AUTHORIZED],
            'refunded state' => [PaymentInterface::STATE_REFUNDED],
            'processing reversal state' => [PaymentGraph::STATE_PROCESSING_REVERSAL],
        ];
    }
}
