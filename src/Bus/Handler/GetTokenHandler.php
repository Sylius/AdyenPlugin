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

namespace Sylius\AdyenPlugin\Bus\Handler;

use Sylius\AdyenPlugin\Bus\Command\CreateToken;
use Sylius\AdyenPlugin\Bus\DispatcherInterface;
use Sylius\AdyenPlugin\Bus\Query\GetToken;
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Exception\OrderWithoutCustomerException;
use Sylius\AdyenPlugin\Repository\AdyenTokenRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final class GetTokenHandler
{
    /** @var AdyenTokenRepositoryInterface */
    private $adyenTokenRepository;

    /** @var DispatcherInterface */
    private $dispatcher;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    public function __construct(
        AdyenTokenRepositoryInterface $adyenTokenRepository,
        DispatcherInterface $dispatcher,
        TokenStorageInterface $tokenStorage,
    ) {
        $this->adyenTokenRepository = $adyenTokenRepository;
        $this->dispatcher = $dispatcher;
        $this->tokenStorage = $tokenStorage;
    }

    private function getUser(): ?UserInterface
    {
        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            return null;
        }

        $user = $token->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    public function __invoke(GetToken $getTokenQuery): ?AdyenTokenInterface
    {
        if (null === $this->getUser()) {
            return null;
        }

        $customer = $getTokenQuery->getOrder()->getCustomer();
        if (null === $customer) {
            throw new OrderWithoutCustomerException($getTokenQuery->getOrder());
        }

        Assert::isInstanceOf(
            $customer,
            CustomerInterface::class,
            'Customer doesn\'t implement a core CustomerInterface',
        );

        $token = $this->adyenTokenRepository->findOneByPaymentMethodAndCustomer(
            $getTokenQuery->getPaymentMethod(),
            $customer,
        );

        if (null !== $token) {
            return $token;
        }

        return $this->dispatcher->dispatch(
            new CreateToken($getTokenQuery->getPaymentMethod(), $customer),
        );
    }
}
