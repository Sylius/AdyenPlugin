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

namespace Sylius\AdyenPlugin\Normalizer;

use Sylius\AdyenPlugin\Resolver\Product\ThumbnailUrlResolverInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class OrderItemToLineItemNormalizer extends AbstractPaymentNormalizer
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        private ThumbnailUrlResolverInterface $thumbnailUrlResolver,
        private string $defaultLocale = 'en_US',
    ) {
    }

    /**
     * @param mixed|OrderItemInterface $data
     */
    public function supportsNormalization(
        $data,
        ?string $format = null,
        array $context = [],
    ): bool {
        return parent::supportsNormalization($data, $format, $context) && $data instanceof OrderItemInterface;
    }

    /**
     * @param mixed $object
     */
    public function normalize(
        $object,
        ?string $format = null,
        array $context = [],
    ): array {
        Assert::isInstanceOf($object, OrderItemInterface::class);

        /** @var OrderInterface $order */
        $order = $object->getOrder();

        $locale = $this->getLocale($order);

        $amountWithoutTax = $object->getTotal() - $object->getTaxTotal();
        $productVariant = $object->getVariant();

        Assert::notNull($productVariant);
        $product = $productVariant->getProduct();

        Assert::notNull($product);

        $name = $productVariant->getTranslation($locale)->getName() ?? $product->getTranslation($locale)->getName();

        return [
            'description' => $name,
            'amountIncludingTax' => $object->getTotal(),
            'amountExcludingTax' => $amountWithoutTax,
            'quantity' => $object->getQuantity(),
            'id' => $object->getId(),
            'productUrl' => $this->urlGenerator->generate('sylius_shop_product_show', [
                'slug' => (string) $product->getTranslation($locale)->getSlug(),
                '_locale' => $locale,
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'imageUrl' => $this->thumbnailUrlResolver->resolve($productVariant),
        ];
    }

    private function getLocale(?OrderInterface $order): string
    {
        return
            $order?->getLocaleCode() ??
            $this->requestStack->getCurrentRequest()?->getLocale() ??
            $this->defaultLocale
        ;
    }
}
