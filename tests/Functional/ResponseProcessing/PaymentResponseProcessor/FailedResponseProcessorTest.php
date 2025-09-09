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

use Sylius\AdyenPlugin\Bus\Command\PaymentLifecycleCommand;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\AdyenPlugin\Client\ResponseStatus;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\FailedResponseProcessor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Tests\Sylius\AdyenPlugin\Unit\Mock\RequestMother;

class FailedResponseProcessorTest extends AbstractProcessor
{
    private const TOKEN_VALUE = 'Szczebrzeszyn';

    /** @var MessageBusInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $messageBus;

    /** @var PaymentCommandFactoryInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $paymentCommandFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->paymentCommandFactory = $this->createMock(PaymentCommandFactoryInterface::class);

        $this->processor = new FailedResponseProcessor(
            self::getRouter($this->getContainer()),
            $this->getContainer()->get('translator'),
            $this->messageBus,
            $this->paymentCommandFactory,
        );
    }

    public function testProcess(): void
    {
        $payment = $this->getPayment('authorized', self::TOKEN_VALUE);

        $request = RequestMother::createWithSession();

        $paymentStatusReceivedCommand = $this->createMock(PaymentLifecycleCommand::class);
        $this->paymentCommandFactory
            ->expects($this->once())
            ->method('createForEvent')
            ->with(ResponseStatus::PAYMENT_STATUS_RECEIVED, $payment)
            ->willReturn($paymentStatusReceivedCommand);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($paymentStatusReceivedCommand)
            ->willReturn(Envelope::wrap(new \stdClass(), [new HandledStamp(true, static::class)]))
        ;

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
