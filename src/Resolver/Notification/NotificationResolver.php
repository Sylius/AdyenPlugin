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

namespace Sylius\AdyenPlugin\Resolver\Notification;

use Psr\Log\LoggerInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class NotificationResolver implements NotificationResolverInterface
{
    /**
     * Adyen passes booleans as strings
     */
    private const DENORMALIZATION_FORMAT = 'json';

    public function __construct(
        private readonly DenormalizerInterface $denormalizer,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return NotificationItemData[] */
    public function resolve(string $paymentCode, Request $request): array
    {
        $result = [];
        foreach ($this->denormalizeRequestData($request) as $item) {
            $item->paymentCode = $paymentCode;

            $violations = $this->validator->validate($item);
            if ($violations->count() > 0) {
                $this->logger->warning(
                    'Invalid notification item received from Adyen: ' . $this->formatViolations($violations)
                );
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @return NotificationItemData[]
     */
    private function denormalizeRequestData(Request $request): array
    {
        $payload = $request->request->all();

        /** @var array $notificationItems */
        $notificationItems = $payload['notificationItems'];
        $result = [];

        /** @var array $notificationItem */
        foreach ($notificationItems as $notificationItem) {
            /** @var array $notificationRequestItem */
            $notificationRequestItem = $notificationItem['NotificationRequestItem'] ?? [];

            $result[] = $this->denormalizer->denormalize(
                $notificationRequestItem,
                NotificationItemData::class,
                self::DENORMALIZATION_FORMAT,
            );
        }

        return $result;
    }

    private function formatViolations(ConstraintViolationListInterface $violations): string
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf(
                '%s: %s (given: %s)',
                $violation->getPropertyPath(),
                $violation->getMessage(),
                $violation->getInvalidValue(),
            );
        }

        return implode('; ', $messages);
    }
}
