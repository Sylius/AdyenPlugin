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

namespace Tests\Sylius\AdyenPlugin\Unit\Normalizer;

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Normalizer\ShippingLineGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Sylius\AdyenPlugin\Unit\OrderMother;

class ShippingLineGeneratorTest extends TestCase
{
    public const ITEM_NAME = 'Pacanów';

    /** @var \PHPUnit\Framework\MockObject\MockObject|TranslatorInterface */
    private $translator;

    /** @var ShippingLineGenerator */
    private $generator;

    protected function setUp(): void
    {
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->generator = new ShippingLineGenerator($this->translator);
    }

    public function testGenerating(): void
    {
        $this->setupTranslator();

        $entries = [
            ['amountExcludingTax' => 24, 'amountIncludingTax' => 30],
            ['amountExcludingTax' => 35, 'amountIncludingTax' => 42],
        ];

        $order = OrderMother::createForNormalization();

        $result = $this->generator->generate($entries, $order);
        $this->assertEquals([
            'amountExcludingTax' => 25,
            'amountIncludingTax' => 32,
            'description' => self::ITEM_NAME,
            'quantity' => 1,
        ], $result);
    }

    private function setupTranslator(): void
    {
        $this->translator
            ->method('trans')
            ->willReturn(self::ITEM_NAME)
        ;
    }
}
