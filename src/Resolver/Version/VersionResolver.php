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

use PackageVersions\FallbackVersions;

final class VersionResolver implements VersionResolverInterface
{
    private const PACKAGE_NAME = 'sylius/adyen-plugin';

    private const TEST_APPLICATION_VERSION = 'dev';

    private function getPluginVersion(): string
    {
        try {
            if (class_exists(\Composer\InstalledVersions::class)) {
                return \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) ?? self::TEST_APPLICATION_VERSION;
            }

            return substr(
                FallbackVersions::getVersion(self::PACKAGE_NAME),
                0,
                -1,
            );
        } catch (\Exception $ex) {
            return self::TEST_APPLICATION_VERSION;
        }
    }

    private function resolveApplicationInfo(): array
    {
        $syliusVersion = '';
        if (5 === constant('Symfony\Component\HttpKernel\Kernel::MAJOR_VERSION')) {
            /** @var string $syliusVersion */
            $syliusVersion = constant('Sylius\Bundle\CoreBundle\Application\Kernel::VERSION');
        } elseif (defined('Sylius\Bundle\CoreBundle\SyliusCoreBundle::VERSION')) {
            /** @var string $syliusVersion */
            $syliusVersion = constant('Sylius\Bundle\CoreBundle\SyliusCoreBundle::VERSION');
        }

        return [
            'merchantApplication' => [
                'name' => 'adyen-sylius',
                'version' => $this->getPluginVersion(),
            ],
            'externalPlatform' => [
                'name' => 'Sylius',
                'version' => $syliusVersion,
                'integrator' => 'Sylius',
            ],
        ];
    }

    public function appendVersionConstraints(array $payload): array
    {
        $payload['applicationInfo'] = $this->resolveApplicationInfo();

        return $payload;
    }
}
