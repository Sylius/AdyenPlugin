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

namespace Sylius\AdyenPlugin\Client;

use Adyen\Client;
use Adyen\Environment;
use Adyen\HttpClient\ClientInterface;
use Adyen\HttpClient\CurlClient;
use Sylius\AdyenPlugin\Resolver\Configuration\ConfigurationResolver;

final class AdyenTransportFactory implements AdyenTransportFactoryInterface
{
    /** @var ClientInterface */
    private $adyenHttpClient;

    public function __construct(
        ?ClientInterface $adyenHttpClient = null,
    ) {
        $this->adyenHttpClient = $adyenHttpClient ?? new CurlClient();
    }

    public function create(array $options): Client
    {
        $options = (new ConfigurationResolver())->resolve($options);

        $client = new Client();
        $client->setHttpClient($this->adyenHttpClient);

        $client->setXApiKey($options['apiKey']);
        if (AdyenClientInterface::TEST_ENVIRONMENT == $options['environment']) {
            $client->setEnvironment(Environment::TEST);
        } else {
            /** @var string $prefix */
            $prefix = $options['liveEndpointUrlPrefix'];
            $client->setEnvironment(Environment::LIVE, $prefix);
        }
        $client->setTimeout(30);

        return $client;
    }
}
