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

namespace Sylius\AdyenPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class SyliusAdyenExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    public const SYLIUS_ADYEN_PAYMENT_METHODS_ALLOWED_TYPES = 'sylius_adyen.payment_methods.allowed_types';

    public const SYLIUS_ADYEN_PAYMENT_METHODS_MANUAL_CAPTURE_SUPPORTING_TYPES = 'sylius_adyen.payment_methods.manual_capture_supporting_types';

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Sylius\AdyenPlugin\Migrations' => __DIR__ . '/../Migrations',
            ],
        ]);

        $container->prependExtensionConfig('sylius_labs_doctrine_migrations_extra', [
            'migrations' => [
                'Sylius\AdyenPlugin\Migrations' => [
                    'Sylius\Bundle\CoreBundle\Migrations',
                    'Sylius\RefundPlugin\Migrations',
                ],
            ],
        ]);
    }

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.xml');

        $allowedTypes = $mergedConfig['payment_methods']['allowed_types'];
        $manualCaptureSupportingTypes = $mergedConfig['payment_methods']['manual_capture_supporting_types'];

        $container->setParameter(self::SYLIUS_ADYEN_PAYMENT_METHODS_ALLOWED_TYPES, $allowedTypes);
        $container->setParameter(self::SYLIUS_ADYEN_PAYMENT_METHODS_MANUAL_CAPTURE_SUPPORTING_TYPES, $manualCaptureSupportingTypes);
        $container->setParameter('sylius_adyen.esd.supported_currencies', $mergedConfig['esd']['supported_currencies']);
        $container->setParameter('sylius_adyen.esd.supported_countries', $mergedConfig['esd']['supported_countries']);
        $container->setParameter('sylius_adyen.esd.supported_card_brands', $mergedConfig['esd']['supported_card_brands']);
        $container->setParameter('sylius_adyen.integrator_name', $mergedConfig['integrator_name']);
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function getAlias(): string
    {
        return 'sylius_adyen';
    }
}
