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

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\SuccessfulResponseProcessor;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Tests\Sylius\AdyenPlugin\Unit\Mock\RequestMother;

class SuccessfulResponseProcessorTest extends AbstractProcessor
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new SuccessfulResponseProcessor(
            self::getRouter(self::getContainer()),
            self::getContainer()->get('translator'),
        );
    }

    public static function provideForTestAccepts(): array
    {
        return [
            'affirmative' => ['authorised', true],
            'negative' => ['refused', false],
        ];
    }

    public static function provideForTestRedirect(): array
    {
        return [
            'generic' => [
                RequestMother::createWithSessionForDefinedOrderId(),
                'thank-you',
            ],
            'alternative' => [
                RequestMother::createWithSessionForSpecifiedQueryToken(),
                '/orders/',
                true,
            ],
        ];
    }

    #[DataProvider('provideForTestRedirect')]
    public function testRedirect(
        Request $request,
        string $expectedUrlEnding,
        bool $expectFlash = false,
    ) {
        $payment = $this->createMock(PaymentInterface::class);
        $result = $this->processor->process('Szczebrzeszyn', $request, $payment);

        self::assertStringEndsWith($expectedUrlEnding, $result);

        if (!$expectFlash) {
            return;
        }

        self::assertNotEmpty($request->getSession()->getFlashbag()->get('info'));
    }
}
