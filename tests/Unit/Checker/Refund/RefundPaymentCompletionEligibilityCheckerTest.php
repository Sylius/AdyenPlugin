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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker\Refund;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Checker\Refund\RefundPaymentCompletionEligibilityChecker;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;

final class RefundPaymentCompletionEligibilityCheckerTest extends TestCase
{
    private AdyenPaymentMethodCheckerInterface&MockObject $adyenPaymentMethodChecker;

    private RefundPaymentCompletionEligibilityChecker $checker;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->checker = new RefundPaymentCompletionEligibilityChecker($this->adyenPaymentMethodChecker);
    }

    public function testItReturnsTrue_WhenPaymentMethodIsNotAdyenPaymentMethod(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);

        $refundPayment
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->willReturn($paymentMethod)
        ;

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPaymentMethod')
            ->with($paymentMethod)
            ->willReturn(false)
        ;

        $result = $this->checker->canBeCompleted($refundPayment);

        self::assertTrue($result);
    }

    public function testItReturnsFalse_WhenPaymentMethodIsAdyenPaymentMethod(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $refundPayment = $this->createMock(RefundPaymentInterface::class);

        $refundPayment
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->willReturn($paymentMethod)
        ;

        $this->adyenPaymentMethodChecker
            ->expects(self::once())
            ->method('isAdyenPaymentMethod')
            ->with($paymentMethod)
            ->willReturn(true)
        ;

        $result = $this->checker->canBeCompleted($refundPayment);

        self::assertFalse($result);
    }
}
