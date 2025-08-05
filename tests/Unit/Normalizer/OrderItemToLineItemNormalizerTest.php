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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Normalizer\AbstractPaymentNormalizer;
use Sylius\AdyenPlugin\Normalizer\OrderItemToLineItemNormalizer;
use Sylius\AdyenPlugin\Resolver\Product\ThumbnailUrlResolverInterface;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tests\Sylius\AdyenPlugin\Unit\Mock\RequestMother;
use Tests\Sylius\AdyenPlugin\Unit\OrderMother;

class OrderItemToLineItemNormalizerTest extends TestCase
{
    private const EXPECTED_PERMALINK = 'https://example.com';

    private const EXPECTED_IMAGE_LINK = 'https://example.com/42.jpg';

    /** @var RequestStack */
    private $requestStack;

    /** @var \PHPUnit\Framework\MockObject\MockObject|UrlGeneratorInterface */
    private $urlGenerator;

    /** @var ThumbnailUrlResolverInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $thumbnailUrlResolver;

    /** @var OrderItemToLineItemNormalizer */
    private $normalizer;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->thumbnailUrlResolver = $this->createMock(ThumbnailUrlResolverInterface::class);

        $this->normalizer = new OrderItemToLineItemNormalizer(
            $this->requestStack,
            $this->urlGenerator,
            $this->thumbnailUrlResolver,
        );
    }

    public static function provideForSupportsNormalization(): array
    {
        return [
            'without context and order item' => [[], null, false],
            'without context' => [[], new OrderItem(), false],
            'with context and order item' => [[AbstractPaymentNormalizer::NORMALIZER_ENABLED => 1], new OrderItem(), true],
        ];
    }

    #[DataProvider('provideForSupportsNormalization')]
    public function testSupportsNormalization(
        array $context,
        ?OrderItemInterface $order,
        bool $pass,
    ): void {
        $this->assertEquals($pass, $this->normalizer->supportsNormalization($order, null, $context));
    }

    public function testNormalize(): void
    {
        $this->setupRequest();

        $this->urlGenerator
            ->method('generate')
            ->willReturn(self::EXPECTED_PERMALINK)
        ;

        $this->thumbnailUrlResolver
            ->method('resolve')
            ->willReturn(self::EXPECTED_IMAGE_LINK)
        ;

        $orderItem = OrderMother::createOrderItem();

        $result = $this->normalizer->normalize($orderItem);

        $this->assertEquals([
            'description' => OrderMother::ITEM_VARIANT_NAME,
            'amountIncludingTax' => OrderMother::ITEM_UNIT_PRICE + OrderMother::ITEM_TAX_VALUE,
            'amountExcludingTax' => OrderMother::ITEM_UNIT_PRICE,
            'quantity' => 1,
            'id' => OrderMother::ITEM_ID,
            'productUrl' => self::EXPECTED_PERMALINK,
            'imageUrl' => self::EXPECTED_IMAGE_LINK,
        ], $result);
    }

    private function setupRequest(): void
    {
        $this->requestStack->push(RequestMother::createWithLocaleSet());
    }
}
