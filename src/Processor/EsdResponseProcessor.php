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

namespace Sylius\AdyenPlugin\Processor;

use Psr\Log\LoggerInterface;

final class EsdResponseProcessor implements EsdResponseProcessorInterface
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function processEsdResponse(array $response): void
    {
        $eligibilityLevel = $this->getEsdEligibilityLevel($response);

        if ($eligibilityLevel !== null) {
            $this->logger->info('ESD eligibility detected', [
                'eligibility_level' => $eligibilityLevel,
                'psp_reference' => $response['pspReference'] ?? null,
            ]);
        }

        if ($this->hasEsdValidationErrors($response)) {
            $errors = $this->getEsdValidationErrors($response);
            $this->logger->warning('ESD validation errors detected', [
                'errors' => $errors,
                'psp_reference' => $response['pspReference'] ?? null,
            ]);
        }

        $this->logEsdProcessingInfo($response);
    }

    public function getEsdEligibilityLevel(array $response): ?string
    {
        if (!isset($response['additionalData'])) {
            return null;
        }

        $additionalData = $response['additionalData'];

        if (isset($additionalData['cardSchemeEnhancedDataLevel'])) {
            return $additionalData['cardSchemeEnhancedDataLevel'];
        }

        if (isset($additionalData['enhancedSchemeDataLevel'])) {
            return $additionalData['enhancedSchemeDataLevel'];
        }

        return null;
    }

    public function hasEsdValidationErrors(array $response): bool
    {
        return !empty($this->getEsdValidationErrors($response));
    }

    public function getEsdValidationErrors(array $response): array
    {
        if (!isset($response['additionalData'])) {
            return [];
        }

        $additionalData = $response['additionalData'];
        $errors = [];

        if (isset($additionalData['enhancedSchemeDataValidationError'])) {
            $errors[] = $additionalData['enhancedSchemeDataValidationError'];
        }

        foreach ($additionalData as $key => $value) {
            if (strpos($key, 'enhancedSchemeDataError') === 0 && !empty($value)) {
                $errors[] = $value;
            }
        }

        return $errors;
    }

    private function logEsdProcessingInfo(array $response): void
    {
        if (!isset($response['additionalData'])) {
            return;
        }

        $additionalData = $response['additionalData'];
        $esdInfo = [];

        foreach ($additionalData as $key => $value) {
            if (strpos($key, 'enhancedSchemeData') === 0 ||
                strpos($key, 'cardSchemeEnhanced') === 0) {
                $esdInfo[$key] = $value;
            }
        }

        if (!empty($esdInfo)) {
            $this->logger->info('ESD processing information', [
                'esd_data' => $esdInfo,
                'psp_reference' => $response['pspReference'] ?? null,
            ]);
        }
    }
}
