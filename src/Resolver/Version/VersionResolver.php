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

namespace Sylius\AdyenPlugin\Resolver\Version;

use Composer\InstalledVersions;
use PackageVersions\FallbackVersions;
use Sylius\Bundle\CoreBundle\SyliusCoreBundle;

final class VersionResolver implements VersionResolverInterface
{
    private const PACKAGE_NAME = 'sylius/adyen-plugin';

    private const TEST_APPLICATION_VERSION = 'dev';

    private readonly array $applicationInfo;

    public function __construct()
    {
        $this->applicationInfo = $this->resolveApplicationInfo();
    }

    public function appendVersionConstraints(array $payload): array
    {
        return array_merge($payload, ['applicationInfo' => $this->applicationInfo]);
    }

    private function resolveApplicationInfo(): array
    {
        return [
            'merchantApplication' => [
                'name' => 'Sylius Adyen Plugin',
                'version' => $this->getPluginVersion(),
            ],
            'externalPlatform' => [
                'name' => 'Sylius',
                'version' => SyliusCoreBundle::VERSION,
                'integrator' => 'Sylius',
            ],
        ];
    }

    private function getPluginVersion(): string
    {
        try {
            if (class_exists(InstalledVersions::class)) {
                return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) ?? self::TEST_APPLICATION_VERSION;
            }

            return substr(FallbackVersions::getVersion(self::PACKAGE_NAME), 0, -1);
        } catch (\Exception) {
            return self::TEST_APPLICATION_VERSION;
        }
    }
}
