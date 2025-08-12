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

namespace Sylius\AdyenPlugin\Resolver\Configuration;

use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigurationResolver
{
    public function resolve(array $configuredOptions): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'apiKey' => '',
            'clientKey' => '',
            'hmacKey' => '',
            'authUser' => '',
            'authPassword' => '',
            'environment' => AdyenClientInterface::TEST_ENVIRONMENT,
            'adyen' => 0,
            'merchantAccount' => '',
            'liveEndpointUrlPrefix' => '',
            'esdEnabled' => false,
            'esdType' => 'level3',
            'merchantCategoryCode' => '',
        ]);
        $resolver->setRequired($resolver->getDefinedOptions());

        return $resolver->resolve($configuredOptions);
    }
}
