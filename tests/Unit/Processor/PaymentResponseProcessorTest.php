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

namespace Tests\Sylius\AdyenPlugin\Unit\Processor;

use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\ProcessorInterface;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessorInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\Sylius\AdyenPlugin\Unit\Processor\PaymentResponseProcessor\AbstractProcessor;

class PaymentResponseProcessorTest extends KernelTestCase
{
    private const URL_ENDING = 'thank-you';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    private function getPaymentResponseProcessor(array $processors = []): PaymentResponseProcessorInterface
    {
        return new PaymentResponseProcessor(
            $processors,
            AbstractProcessor::getRouter($this->getContainer()),
        );
    }

    private function getProcessor(bool $accepts, ?string $response = null): ProcessorInterface
    {
        $result = $this->createMock(ProcessorInterface::class);
        if ($accepts) {
            $result
                ->method('accepts')
                ->willReturn($accepts)
            ;
        }

        if (null !== $response) {
            $result
                ->method('process')
                ->willReturn($response)
            ;
        }

        return $result;
    }

    public function testForNoAcceptingProcessor(): void
    {
        $tested = $this->getPaymentResponseProcessor([$this->getProcessor(false)]);

        $result = $tested->process('code', Request::create('/'), null);

        $this->assertStringEndsWith(self::URL_ENDING, $result);
    }

    public function testAcceptingProcessor(): void
    {
        $payment = $this->createMock(PaymentInterface::class);

        $tested = $this->getPaymentResponseProcessor([$this->getProcessor(true, self::URL_ENDING)]);

        $result = $tested->process('code', Request::create('/'), $payment);
        $this->assertEquals(self::URL_ENDING, $result);
    }
}
