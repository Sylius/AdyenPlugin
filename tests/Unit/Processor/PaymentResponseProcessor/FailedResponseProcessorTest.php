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

namespace Tests\Sylius\AdyenPlugin\Unit\Processor\PaymentResponseProcessor;

use Sylius\AdyenPlugin\Bus\DispatcherInterface;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\FailedResponseProcessor;
use Tests\Sylius\AdyenPlugin\Unit\Mock\RequestMother;

class FailedResponseProcessorTest extends AbstractProcessor
{
    private const TOKEN_VALUE = 'Szczebrzeszyn';

    /** @var DispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->createMock(DispatcherInterface::class);

        $this->processor = new FailedResponseProcessor(
            self::getRouter($this->getContainer()),
            $this->getContainer()->get('translator'),
            $this->dispatcher,
        );
    }

    public function testProcess(): void
    {
        $payment = $this->getPayment('authorized', self::TOKEN_VALUE);

        $request = RequestMother::createWithSession();

        $result = $this->processor->process('code', $request, $payment);

        $this->assertStringEndsWith(self::TOKEN_VALUE, $result);
        $this->assertNotEmpty($request->getSession()->getFlashBag()->get('error'));
    }

    public static function provideForTestAccepts(): array
    {
        return [
            'affirmative' => ['authorized', false],
            'negative' => ['refused', true],
        ];
    }
}
