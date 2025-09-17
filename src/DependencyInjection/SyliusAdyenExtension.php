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

        $this->setPaymentMethodsParameters($mergedConfig, $container);
        $this->setEsdParameters($mergedConfig, $container);

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

    private function setPaymentMethodsParameters(array $config, ContainerBuilder $container): void
    {
        $container->setParameter(
            'sylius_adyen.payment_methods.allowed_types',
            $config['payment_methods']['allowed_types'],
        );
        $container->setParameter(
            'sylius_adyen.payment_methods.manual_capture_supporting_types',
            $this->mergeUniquely(
                $config['payment_methods']['manual_capture_supporting_types'],
                $config['payment_methods']['only_manual_capture_types'],
            ),
        );
        $container->setParameter(
            'sylius_adyen.payment_methods.only_for_logged_in_users_types',
            $config['payment_methods']['only_for_logged_in_users_types'],
        );
        $container->setParameter(
            'sylius_adyen.payment_methods.only_manual_capture_types',
            $config['payment_methods']['only_manual_capture_types'],
        );
    }

    private function setEsdParameters(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('sylius_adyen.esd.supported_currencies', $config['esd']['supported_currencies']);
        $container->setParameter('sylius_adyen.esd.supported_countries', $config['esd']['supported_countries']);
        $container->setParameter('sylius_adyen.esd.supported_card_brands', $config['esd']['supported_card_brands']);
    }

    /**
     * @param string[] $first
     * @param string[] $second
     *
     * @return string[]
     */
    private function mergeUniquely(array $first, array $second): array
    {
        return array_values(array_unique(array_merge($first, $second)));
    }
}
