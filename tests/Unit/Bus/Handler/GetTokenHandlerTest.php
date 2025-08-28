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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\CreateToken;
use Sylius\AdyenPlugin\Bus\Handler\GetTokenHandler;
use Sylius\AdyenPlugin\Bus\Query\GetToken;
use Sylius\AdyenPlugin\Entity\AdyenToken;
use Sylius\AdyenPlugin\Repository\AdyenTokenRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class GetTokenHandlerTest extends TestCase
{
    /** @var AdyenTokenRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $adyenTokenRepository;

    /** @var MessageBusInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $messageBus;

    /** @var GetTokenHandler */
    private $handler;

    /** @var \PHPUnit\Framework\MockObject\MockObject|TokenStorageInterface */
    private $tokenStorage;

    protected function setUp(): void
    {
        $this->adyenTokenRepository = $this->createMock(AdyenTokenRepositoryInterface::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->handler = new GetTokenHandler($this->adyenTokenRepository, $this->tokenStorage, $this->messageBus);
    }

    public function testForTokenWithoutCustomer(): void
    {
        $this->makeUserAuthenticated();

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)(
            $this->createGetTokenQueryMock()
        );
    }

    public static function provideForTestQuery(): array
    {
        return [
            'for already existing' => [
                true,
            ],
            'for non-existing' => [
                false,
            ],
        ];
    }

    private function makeUserAuthenticated(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn(
                $this->createMock(UserInterface::class),
            )
        ;

        $this
            ->tokenStorage
            ->method('getToken')
            ->willReturn($token)
        ;
    }

    private function setupMocks(
        bool $existingToken,
        PaymentMethodInterface $paymentMethod,
        CustomerInterface $customer,
    ): void {
        $this->makeUserAuthenticated();

        $repositoryMethod = $this->adyenTokenRepository
            ->method('findOneByPaymentMethodAndCustomer')
            ->with($this->equalTo($paymentMethod), $this->equalTo($customer))
        ;

        if ($existingToken) {
            $repositoryMethod->willReturn(new AdyenToken());

            $this->messageBus
                ->expects($this->never())
                ->method('dispatch');

            return;
        }

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn (CreateToken $command) => $command->getPaymentMethod() === $paymentMethod &&
                $command->getCustomer() === $customer))
            ->willReturn(Envelope::wrap(new \stdClass(), [new HandledStamp(new AdyenToken(), static::class)]))
        ;
    }

    #[DataProvider('provideForTestQuery')]
    public function testQuery(bool $existingToken = false): void
    {
        $customer = $this->createMock(CustomerInterface::class);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order
            ->method('getCustomer')
            ->willReturn($customer)
        ;

        $query = new GetToken($paymentMethod, $order);

        $this->setupMocks($existingToken, $paymentMethod, $customer);

        $result = ($this->handler)($query);
        $this->assertInstanceOf(AdyenToken::class, $result);
    }

    public function testForAnonymous(): void
    {
        $result = ($this->handler)(
            $this->createGetTokenQueryMock()
        );
        $this->assertNull($result);
    }

    private function createGetTokenQueryMock(): GetToken
    {
        return new GetToken(
            $this->createMock(PaymentMethodInterface::class),
            $this->createMock(OrderInterface::class),
        );
    }
}
