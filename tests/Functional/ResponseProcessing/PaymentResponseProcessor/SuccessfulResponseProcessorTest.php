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
use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\SuccessfulResponseProcessor;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Tests\Sylius\AdyenPlugin\Unit\Mock\RequestMother;

class SuccessfulResponseProcessorTest extends AbstractProcessor
{
    /** @var MessageBusInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $messageBus;

    /** @var PaymentCommandFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $paymentCommandFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->paymentCommandFactory = $this->createMock(PaymentCommandFactoryInterface::class);

        $this->processor = new SuccessfulResponseProcessor(
            self::getRouter($this->getContainer()),
            $this->getContainer()->get('translator'),
            $this->messageBus,
            $this->paymentCommandFactory,
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

        $paymentStatusReceivedCommand = $this->createMock(PaymentLifecycleCommand::class);
        $this->paymentCommandFactory
            ->expects($this->once())
            ->method('createForEvent')
            ->with(SuccessfulResponseProcessor::PAYMENT_STATUS_RECEIVED_CODE, $payment)
            ->willReturn($paymentStatusReceivedCommand);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($paymentStatusReceivedCommand)
            ->willReturn(Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]))
        ;

        $result = $this->processor->process('Szczebrzeszyn', $request, $payment);

        $this->assertStringEndsWith($expectedUrlEnding, $result);

        if (!$expectFlash) {
            return;
        }

        $this->assertNotEmpty($request->getSession()->getFlashbag()->get('info'));
    }
}
