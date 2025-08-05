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

use PHPUnit\Framework\MockObject\MockObject;
use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;

trait AdyenClientTrait
{
    /** @var AdyenClientProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $adyenClientProvider;

    /** @var AdyenClientInterface|MockObject */
    private $adyenClient;

    private function setupAdyenClientMocks(): void
    {
        $this->adyenClient = $this->createMock(AdyenClientInterface::class);
        $this->adyenClientProvider = $this->createMock(AdyenClientProviderInterface::class);
        $this
            ->adyenClientProvider
            ->method('getForPaymentMethod')
            ->willReturn($this->adyenClient)
        ;
    }
}
