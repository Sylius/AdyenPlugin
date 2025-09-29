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

namespace Tests\Sylius\AdyenPlugin\Unit\Twig\Extension;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\RefundEligibilityCheckerInterface;
use Sylius\AdyenPlugin\Twig\Extension\RefundEligibilityExtension;
use Sylius\Component\Core\Model\OrderInterface;
use Twig\TwigFunction;

final class RefundEligibilityExtensionTest extends TestCase
{
    private MockObject|RefundEligibilityCheckerInterface $refundEligibilityChecker;

    private RefundEligibilityExtension $extension;

    protected function setUp(): void
    {
        $this->refundEligibilityChecker = $this->createMock(RefundEligibilityCheckerInterface::class);
        $this->extension = new RefundEligibilityExtension($this->refundEligibilityChecker);
    }

    public function testRegisteredFunctionsAreCorrect(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertEquals('sylius_adyen_can_refund', $functions[0]->getName());

        $callable = $functions[0]->getCallable();
        $this->assertIsArray($callable);
        $this->assertSame($this->extension, $callable[0]);
        $this->assertSame('canRefund', $callable[1]);
    }

    public function testCanRefundReturnsTrue(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $this->refundEligibilityChecker
            ->expects($this->once())
            ->method('canRefund')
            ->with($order)
            ->willReturn(true)
        ;

        $this->assertTrue($this->extension->canRefund($order));
    }

    public function testCanRefundReturnsFalse(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $this->refundEligibilityChecker
            ->expects($this->once())
            ->method('canRefund')
            ->with($order)
            ->willReturn(false)
        ;

        $this->assertFalse($this->extension->canRefund($order));
    }
}
