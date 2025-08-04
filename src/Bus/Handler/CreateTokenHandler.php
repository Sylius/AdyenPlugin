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
use Sylius\AdyenPlugin\Entity\AdyenTokenInterface;
use Sylius\AdyenPlugin\Factory\AdyenTokenFactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateTokenHandler
{
    /** @var AdyenTokenFactoryInterface */
    private $tokenFactory;

    /** @var RepositoryInterface */
    private $tokenRepository;

    public function __construct(
        AdyenTokenFactoryInterface $tokenFactory,
        RepositoryInterface $tokenRepository,
    ) {
        $this->tokenFactory = $tokenFactory;
        $this->tokenRepository = $tokenRepository;
    }

    public function __invoke(CreateToken $createToken): AdyenTokenInterface
    {
        $token = $this->tokenFactory->create(
            $createToken->getPaymentMethod(),
            $createToken->getCustomer(),
        );

        $this->tokenRepository->add($token);

        return $token;
    }
}
