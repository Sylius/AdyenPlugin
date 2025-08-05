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

use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\CreateToken;
use Sylius\AdyenPlugin\Bus\Handler\CreateTokenHandler;
use Sylius\AdyenPlugin\Entity\AdyenToken;
use Sylius\AdyenPlugin\Factory\AdyenTokenFactoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class CreateTokenHandlerTest extends TestCase
{
    /** @var AdyenTokenFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $adyenTokenFactory;

    /** @var CreateTokenHandler */
    private $handler;

    /** @var mixed|\PHPUnit\Framework\MockObject\MockObject|EntityRepository */
    private $tokenRepository;

    protected function setUp(): void
    {
        $this->adyenTokenFactory = $this->createMock(AdyenTokenFactoryInterface::class);
        $this->tokenRepository = $this->createMock(EntityRepository::class);
        $this->handler = new CreateTokenHandler($this->adyenTokenFactory, $this->tokenRepository);
    }

    public function testProcess(): void
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $customer = $this->createMock(CustomerInterface::class);

        $request = new CreateToken($paymentMethod, $customer);
        $token = new AdyenToken();

        $this->adyenTokenFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                $this->equalTo($paymentMethod),
                $this->equalTo($customer),
            )
            ->willReturn($token)
        ;

        $this->tokenRepository
            ->expects($this->once())
            ->method('add')
            ->with(
                $this->equalTo($token),
            )
        ;

        $result = ($this->handler)($request);
        $this->assertEquals($token, $result);
    }
}
