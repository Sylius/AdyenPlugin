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

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

abstract class AbstractPaymentNormalizer implements NormalizerInterface
{
    public const NORMALIZER_ENABLED = 'sylius_adyen_payment_normalizer';

    public function supportsNormalization(
        $data,
        ?string $format = null,
        array $context = [],
    ): bool {
        return isset($context[self::NORMALIZER_ENABLED]);
    }
}
