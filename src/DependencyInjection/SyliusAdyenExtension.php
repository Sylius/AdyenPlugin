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
    public const TRANSPORT_FACTORY_ID = 'sylius_adyen.client.adyen_transport_factory';

    public const SUPPORTED_PAYMENT_METHODS_LIST = 'sylius_adyen.supported_payment_methods';

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Sylius\AdyenPlugin\Migrations' => __DIR__ . '/../Migrations',
            ],
        ]);

        $container->prependExtensionConfig('sylius_labs_doctrine_migrations_extra', [
            'migrations' => [
                'Sylius\AdyenPlugin\Migrations' => ['Sylius\Bundle\CoreBundle\Migrations', 'Sylius\RefundPlugin\Migrations'],
            ],
        ]);
    }

    public function loadInternal(array $config, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.xml');

        $container->setParameter(self::SUPPORTED_PAYMENT_METHODS_LIST, (array) $config['supported_types']);
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
