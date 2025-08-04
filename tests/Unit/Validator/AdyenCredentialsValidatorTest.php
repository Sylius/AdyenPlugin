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

namespace Tests\Sylius\AdyenPlugin\Unit\Validator;

use Adyen\HttpClient\ClientInterface;
use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Client\AdyenTransportFactory;
use Sylius\AdyenPlugin\Validator\Constraint\AdyenCredentials;
use Sylius\AdyenPlugin\Validator\Constraint\AdyenCredentialsValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class AdyenCredentialsValidatorTest extends ConstraintValidatorTestCase
{
    /** @var ClientInterface */
    private $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);

        parent::setUp();
    }

    protected function createValidator(): AdyenCredentialsValidator
    {
        return new AdyenCredentialsValidator(new AdyenTransportFactory($this->client));
    }

    public function testAffirmative(): void
    {
        $constraint = new AdyenCredentials();
        $this->validator->validate([
            'environment' => AdyenClientInterface::TEST_ENVIRONMENT,
            'merchantAccount' => 'mer',
            'apiKey' => 'api',
            'liveEndpointUrlPrefix' => 'prefix',
        ], $constraint);

        $this->assertNoViolation();
    }
}
