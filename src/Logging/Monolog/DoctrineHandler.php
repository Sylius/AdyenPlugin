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

namespace Sylius\AdyenPlugin\Logging\Monolog;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Sylius\AdyenPlugin\Factory\LogFactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class DoctrineHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly LogFactoryInterface $logFactory,
        private readonly RepositoryInterface $repository,
    ) {
        parent::__construct();
    }

    protected function write(LogRecord $record): void
    {
        $log = $this->logFactory->create($record['message'], $record['level'], 0);

        $this->repository->add($log);
    }
}
