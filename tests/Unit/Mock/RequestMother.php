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

namespace Tests\Sylius\AdyenPlugin\Unit\Mock;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class RequestMother
{
    public const TEST_LOCALE = 'pl_PL';

    public const WHERE_YOUR_HOME_IS = '127.0.0.1';

    public static function createWithSession(): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        return $request;
    }

    public static function createWithLocaleSet(): Request
    {
        $result = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => self::WHERE_YOUR_HOME_IS]);
        $result->setLocale(self::TEST_LOCALE);

        return $result;
    }
}
