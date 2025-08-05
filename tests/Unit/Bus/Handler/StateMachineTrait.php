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

namespace Tests\Sylius\AdyenPlugin\Unit\Bus\Handler;

use SM\Factory\FactoryInterface;
use SM\StateMachine\StateMachine;

trait StateMachineTrait
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|FactoryInterface */
    private $stateMachineFactory;

    /** @var \PHPUnit\Framework\MockObject\MockObject|StateMachine */
    private $stateMachine;

    private function setupStateMachineMocks(): void
    {
        $this->stateMachine = $this->createMock(StateMachine::class);

        $this->stateMachineFactory = $this->createMock(FactoryInterface::class);
        $this->stateMachineFactory
            ->method('get')
            ->willReturn($this->stateMachine)
        ;
    }
}
