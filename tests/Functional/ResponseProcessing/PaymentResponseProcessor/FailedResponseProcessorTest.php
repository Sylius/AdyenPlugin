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

namespace Tests\Sylius\AdyenPlugin\Functional\ResponseProcessing\PaymentResponseProcessor;

use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\FailedResponseProcessor;
use Tests\Sylius\AdyenPlugin\Unit\Mock\RequestMother;

class FailedResponseProcessorTest extends AbstractProcessor
{
    private const TOKEN_VALUE = 'Szczebrzeszyn';

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new FailedResponseProcessor(
            self::getRouter($this->getContainer()),
            self::getContainer()->get('translator'),
        );
    }

    public function testProcess(): void
    {
        $payment = $this->getPayment('authorized', self::TOKEN_VALUE);

        $request = RequestMother::createWithSession();
        $result = $this->processor->process('code', $request, $payment);

        self::assertStringEndsWith(self::TOKEN_VALUE, $result);
        self::assertNotEmpty($request->getSession()->getFlashBag()->get('error'));
    }

    public static function provideForTestAccepts(): array
    {
        return [
            'affirmative' => ['authorized', false],
            'negative' => ['refused', true],
        ];
    }
}
