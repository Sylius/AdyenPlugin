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
use Sylius\AdyenPlugin\Checker\ReverseEligibilityCheckerInterface;
use Sylius\AdyenPlugin\Twig\Extension\ReverseEligibilityExtension;
use Sylius\Component\Core\Model\OrderInterface;
use Twig\TwigFunction;

final class ReverseEligibilityExtensionTest extends TestCase
{
    private MockObject|ReverseEligibilityCheckerInterface $reverseEligibilityChecker;

    private ReverseEligibilityExtension $extension;

    protected function setUp(): void
    {
        $this->reverseEligibilityChecker = $this->createMock(ReverseEligibilityCheckerInterface::class);
        $this->extension = new ReverseEligibilityExtension($this->reverseEligibilityChecker);
    }

    public function testRegisteredFunctionsAreCorrect(): void
    {
        $functions = $this->extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertInstanceOf(TwigFunction::class, $functions[0]);
        $this->assertEquals('sylius_adyen_can_reverse', $functions[0]->getName());

        $callable = $functions[0]->getCallable();
        $this->assertIsArray($callable);
        $this->assertSame($this->extension, $callable[0]);
        $this->assertSame('canReverse', $callable[1]);
    }

    public function testCanReverseReturnsTrue(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $this->reverseEligibilityChecker
            ->expects($this->once())
            ->method('canReverse')
            ->with($order)
            ->willReturn(true)
        ;

        $this->assertTrue($this->extension->canReverse($order));
    }

    public function testCanReverseReturnsFalse(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $this->reverseEligibilityChecker
            ->expects($this->once())
            ->method('canReverse')
            ->with($order)
            ->willReturn(false)
        ;

        $this->assertFalse($this->extension->canReverse($order));
    }
}
