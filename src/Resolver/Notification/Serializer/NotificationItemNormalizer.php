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

namespace Sylius\AdyenPlugin\Resolver\Notification\Serializer;

use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Webmozart\Assert\Assert;

final class NotificationItemNormalizer implements DenormalizerAwareInterface, DenormalizerInterface, NormalizerAwareInterface
{
    private const DENORMALIZATION_PROCESSED_FLAG = '_adyen_notification_denormalization_processed';

    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    public function denormalize(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): mixed {
        if (!isset($data[self::DENORMALIZATION_PROCESSED_FLAG]) && is_array($data)) {
            $data['eventCode'] = strtolower((string) $data['eventCode']);
            $data['success'] = 'true' === $data['success'];
            $data[self::DENORMALIZATION_PROCESSED_FLAG] = true;
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return
            NotificationItemData::class === $type &&
            isset($data['eventCode'], $data['paymentMethod']) &&
            !isset($data[self::DENORMALIZATION_PROCESSED_FLAG])
        ;
    }

    /**
     * @param NotificationItemData|mixed $object
     *
     * @return array<string, mixed>
     */
    public function normalize(
        $object,
        ?string $format = null,
        array $context = [],
    ) {
        if (!isset($context[$this->getNormalizationMarking($object)])) {
            $context[$this->getNormalizationMarking($object)] = true;
        }

        /**
         * @var array<string, mixed> $result
         */
        $result = $this->normalizer->normalize($object, $format, $context);
        $result['eventCode'] = strtoupper((string) $result['eventCode']);

        return $result;
    }

    public function supportsNormalization(
        mixed $data,
        ?string $format = null,
        array $context = [],
    ): bool {
        return
            $data instanceof NotificationItemData &&
            !isset($context[$this->getNormalizationMarking($data)])
        ;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [NotificationItemData::class => true];
    }

    /**
     * @param mixed $object
     */
    private function getNormalizationMarking($object): string
    {
        Assert::isInstanceOf($object, NotificationItemData::class);

        return sprintf('%s_%s', self::DENORMALIZATION_PROCESSED_FLAG, spl_object_hash($object));
    }
}
