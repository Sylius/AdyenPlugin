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
use Psr\Log\LoggerInterface;
use Sylius\AdyenPlugin\Resolver\Configuration\ConfigurationResolver;

final class AdyenTransportFactory implements AdyenTransportFactoryInterface
{
    /** @var ClientInterface */
    private $adyenHttpClient;

    /** @var LoggerInterface|null */
    private $logger;

    public function __construct(
        ?LoggerInterface $logger = null,
        ?ClientInterface $adyenHttpClient = null,
    ) {
        $this->logger = $logger;
        $this->adyenHttpClient = $adyenHttpClient ?? new CurlClient();
    }

    public function create(array $options): Client
    {
        $options = (new ConfigurationResolver())->resolve($options);

        $client = new Client();
        $client->setHttpClient($this->adyenHttpClient);

        if (null !== $this->logger) {
            $client->setLogger($this->logger);
        }

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
