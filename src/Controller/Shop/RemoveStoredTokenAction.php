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

namespace Sylius\AdyenPlugin\Controller\Shop;

use Sylius\AdyenPlugin\Exception\TokenRemovalFailureException;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Repository\ShopperReferenceRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RemoveStoredTokenAction
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ShopperReferenceRepositoryInterface $shopperReferenceRepository,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly AdyenClientProviderInterface $adyenClientProvider,
    ) {
    }

    private function getUser(): ShopUserInterface
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            throw TokenRemovalFailureException::forAnonymous();
        }

        $user = $token->getUser();
        if (!$user instanceof ShopUserInterface) {
            throw TokenRemovalFailureException::forAnonymous();
        }

        return $user;
    }

    public function __invoke(
        string $code,
        string $paymentReference,
        Request $request,
    ): Response {
        /**
         * @var ?CustomerInterface $customer
         */
        $customer = $this->getUser()->getCustomer();
        if (null === $customer) {
            throw TokenRemovalFailureException::forAnonymous();
        }

        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($code);

        $token = $this->shopperReferenceRepository->findOneByPaymentMethodAndCustomer($paymentMethod, $customer);
        if (null === $token) {
            throw TokenRemovalFailureException::forNonExistingToken();
        }

        $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);

        $client->removeStoredToken($paymentReference, $token);

        return new Response();
    }
}
