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

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\EsdCardPaymentSupportChecker;
use Sylius\Component\Core\Model\PaymentInterface;

final class EsdCardPaymentSupportCheckerTest extends TestCase
{
    public function testItReturnsTrueForSupportedCardBrandInPayload(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
            ],
        ];

        $this->assertTrue($checker->isSupported($payload));
    }

    public function testItReturnsFalseForUnsupportedCardBrandInPayload(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'discover',
            ],
        ];

        $this->assertFalse($checker->isSupported($payload));
    }

    public function testItReturnsFalseForNonSchemePaymentType(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payload = [
            'paymentMethod' => [
                'type' => 'paypal',
                'brand' => 'visa',
            ],
        ];

        $this->assertFalse($checker->isSupported($payload));
    }

    public function testItReturnsFalseWhenPaymentMethodTypeIsMissing(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payload = [
            'paymentMethod' => [
                'brand' => 'visa',
            ],
        ];

        $this->assertFalse($checker->isSupported($payload));
    }

    public function testItReturnsFalseWhenPaymentMethodBrandIsMissing(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payload = [
            'paymentMethod' => [
                'type' => 'scheme',
            ],
        ];

        $this->assertFalse($checker->isSupported($payload));
    }

    public function testItReturnsTrueForSupportedCardBrandInPaymentDetailsAdditionalData(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn([
                'additionalData' => [
                    'cardBin' => 'mc',
                ],
            ]);

        $this->assertTrue($checker->isSupported([], $payment));
    }

    public function testItReturnsTrueForSupportedCardBrandInPaymentDetailsPaymentMethod(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn([
                'paymentMethod' => [
                    'brand' => 'amex',
                ],
            ]);

        $this->assertTrue($checker->isSupported([], $payment));
    }

    public function testItReturnsFalseForUnsupportedCardBrandInPaymentDetails(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn([
                'additionalData' => [
                    'cardBin' => 'discover',
                ],
            ]);

        $this->assertFalse($checker->isSupported([], $payment));
    }

    public function testItReturnsFalseWhenPaymentDetailsHaveNoBrandInfo(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn([
                'someOtherData' => 'value',
            ]);

        $this->assertFalse($checker->isSupported([], $payment));
    }

    public function testItReturnsFalseWhenBothPayloadAndPaymentAreEmpty(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $this->assertFalse($checker->isSupported([]));
    }

    public function testItPrefersPayloadOverPaymentWhenBothProvided(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
            ],
        ];

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->never())
            ->method('getDetails');

        $this->assertTrue($checker->isSupported($payload, $payment));
    }

    public function testItHandlesCaseInsensitiveCardBrands(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['VISA', 'MC', 'AMEX']);

        $payload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'VISA',
            ],
        ];

        $this->assertTrue($checker->isSupported($payload));
    }

    public function testItHandlesEmptySupportedCardBrandsArray(): void
    {
        $checker = new EsdCardPaymentSupportChecker([]);

        $payload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
            ],
        ];

        $this->assertFalse($checker->isSupported($payload));
    }

    public function testItHandlesPaymentDetailsWithCardBinFallbackToBrand(): void
    {
        $checker = new EsdCardPaymentSupportChecker(['visa', 'mc', 'amex']);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())
            ->method('getDetails')
            ->willReturn([
                'additionalData' => [],
                'paymentMethod' => [
                    'brand' => 'mc',
                ],
            ]);

        $this->assertTrue($checker->isSupported([], $payment));
    }
}
