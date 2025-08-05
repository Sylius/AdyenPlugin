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

    /** @var ?callable */
    private static $httpHandler;

    public function setJsonHandler(?callable $jsonHandler): void
    {
        self::$jsonHandler = $jsonHandler;
    }

    public function setPostHandler(?callable $postHandler): void
    {
        self::$postHandler = $postHandler;
    }

    public function setHttpHandler(?callable $httpHandler): void
    {
        self::$httpHandler = $httpHandler;
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

    public function requestHttp(
        Service $service,
        $requestUrl,
        $params,
        $method,
        $requestOptions = null,
    ) {
        if (null !== self::$httpHandler) {
            return call_user_func(static::$httpHandler, $service, $requestUrl, $params, $method, $requestOptions);
        }

        $client = new CurlClient();

        return $client->requestHttp($service, $requestUrl, $params, $method, $requestOptions);
    }
}
