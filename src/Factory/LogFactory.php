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

namespace Sylius\AdyenPlugin\Factory;

use Sylius\AdyenPlugin\Entity\LogInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

final class LogFactory implements FactoryInterface, LogFactoryInterface
{
    /** @var FactoryInterface */
    private $factory;

    public function __construct(FactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function createNew()
    {
        return $this->factory->createNew();
    }

    public function create(
        string $message,
        int $level,
        int $errorCode,
    ): LogInterface {
        /** @var LogInterface $log */
        $log = $this->createNew();

        $log->setMessage($message);
        $log->setLevel($level);
        $log->setErrorCode($errorCode);
        $log->setDateTime(new \DateTime());

        return $log;
    }
}
