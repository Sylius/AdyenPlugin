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

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class SyliusAdyenExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = $this->getConfiguration([], $container);

        $configs = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.xml');

        $this->setPaymentMethodsParameters($configs, $container);
        $this->setEsdParameters($configs, $container);

        $container->setParameter('sylius_adyen.integrator_name', $configs['integrator_name']);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->getCurrentConfiguration($container);

        $this->registerResources('sylius_adyen', 'doctrine/orm', $config['resources'], $container);

        $this->prependDoctrineMigrations($container);
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }

    public function getAlias(): string
    {
        return 'sylius_adyen';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Sylius\AdyenPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@SyliusAdyenPlugin/src/Migrations';
    }

    /** @return string[] */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
            'Sylius\RefundPlugin\Migrations',
        ];
    }

    /** @return array<string, mixed> */
    private function getCurrentConfiguration(ContainerBuilder $container): array
    {
        $configuration = $this->getConfiguration([], $container);

        $configs = $container->getExtensionConfig($this->getAlias());

        return $this->processConfiguration($configuration, $configs);
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
