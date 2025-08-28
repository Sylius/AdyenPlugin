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

namespace Sylius\AdyenPlugin\Validator\Constraint;

use Adyen\AdyenException;
use Adyen\Service\Checkout;
use Sylius\AdyenPlugin\Client\AdyenTransportFactory;
use Sylius\AdyenPlugin\Exception\AuthenticationException;
use Sylius\AdyenPlugin\Exception\InvalidApiKeyException;
use Sylius\AdyenPlugin\Exception\InvalidMerchantAccountException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

class AdyenCredentialsValidator extends ConstraintValidator
{
    public function __construct(private readonly AdyenTransportFactory $adyenTransportFactory)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        Assert::isInstanceOf($constraint, AdyenCredentials::class);
        Assert::isArray($value);

        try {
            $this->isApiKeyValid(
                (string) $value['environment'],
                (string) $value['merchantAccount'],
                (string) $value['apiKey'],
                (string) $value['liveEndpointUrlPrefix'],
            );
        } catch (InvalidApiKeyException) {
            $this->context->buildViolation($constraint->messageInvalidApiKey)->addViolation();
        } catch (InvalidMerchantAccountException) {
            $this->context->buildViolation($constraint->messageInvalidMerchantAccount)->addViolation();
        }
    }

    /**
     * @throws AuthenticationException|AdyenException
     */
    public function isApiKeyValid(
        string $environment,
        ?string $merchantAccount,
        ?string $apiKey,
        ?string $liveEndpointUrlPrefix,
    ): bool {
        $this->validateArguments($merchantAccount, $apiKey);

        $payload = [
            'merchantAccount' => $merchantAccount,
        ];
        $options = [
            'environment' => $environment,
            'apiKey' => $apiKey,
            'liveEndpointUrlPrefix' => $liveEndpointUrlPrefix,
        ];

        try {
            (new Checkout(
                $this->adyenTransportFactory->create($options),
            ))->paymentMethods($payload);
        } catch (AdyenException $exception) {
            $this->dispatchException($exception);
        }

        return true;
    }

    private function validateArguments(?string $merchantAccount, ?string $apiKey): void
    {
        if (null === $merchantAccount || '' === $merchantAccount) {
            throw new InvalidMerchantAccountException();
        }

        if (null === $apiKey || '' === $apiKey) {
            throw new InvalidApiKeyException();
        }
    }

    private function dispatchException(AdyenException $exception): void
    {
        if (Response::HTTP_UNAUTHORIZED === $exception->getCode()) {
            throw new InvalidApiKeyException();
        }

        if (Response::HTTP_FORBIDDEN === $exception->getCode()) {
            throw new InvalidMerchantAccountException();
        }

        throw $exception;
    }
}
