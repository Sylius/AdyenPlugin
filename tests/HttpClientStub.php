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

namespace Tests\Sylius\AdyenPlugin;

use Adyen\HttpClient\ClientInterface;
use Adyen\HttpClient\CurlClient;
use Adyen\Service;

class HttpClientStub implements ClientInterface
{
    /** @var ?callable */
    private static $jsonHandler;

    /** @var ?callable */
    private static $postHandler;

    public function setJsonHandler(?callable $jsonHandler): void
    {
        self::$jsonHandler = $jsonHandler;
    }

    public function setPostHandler(?callable $postHandler): void
    {
        self::$postHandler = $postHandler;
    }

    public function requestJson(
        Service $service,
        $requestUrl,
        $params,
    ): mixed {
        if (null !== self::$jsonHandler) {
            return call_user_func(static::$jsonHandler, $service, $requestUrl, $params);
        }

        $client = new CurlClient();

        return $client->requestJson($service, $requestUrl, $params);
    }

    public function requestPost(
        Service $service,
        $requestUrl,
        $params,
    ) {
        if (null !== self::$postHandler) {
            return call_user_func(static::$postHandler, $service, $requestUrl, $params);
        }

        $client = new CurlClient();

        return $client->requestPost($service, $requestUrl, $params);
    }
}
