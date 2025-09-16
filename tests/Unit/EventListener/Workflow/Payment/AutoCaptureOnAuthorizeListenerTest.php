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

namespace Tests\Sylius\AdyenPlugin\Unit\EventListener\Workflow\Payment;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\EventListener\Workflow\Payment\AutoCaptureOnAuthorizeListener;
use Sylius\AdyenPlugin\Processor\Payment\AuthorizationStateProcessorInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\Workflow\Event\Event;

final class AutoCaptureOnAuthorizeListenerTest extends TestCase
{
    private AuthorizationStateProcessorInterface&MockObject $processor;
    private AutoCaptureOnAuthorizeListener $listener;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(AuthorizationStateProcessorInterface::class);
        $this->listener = new AutoCaptureOnAuthorizeListener($this->processor);
    }

    public function testItProcessesPaymentWhenSubjectIsPaymentInterface(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn($payment);
        $this->processor->expects($this->once())->method('process')->with($payment);

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNotPaymentInterface(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(new \stdClass());
        $this->processor->expects($this->never())->method('process');

        ($this->listener)($event);
    }

    public function testItDoesNothingWhenSubjectIsNull(): void
    {
        $event = $this->createMock(Event::class);

        $event->expects($this->once())->method('getSubject')->willReturn(null);
        $this->processor->expects($this->never())->method('process');

        ($this->listener)($event);
    }
}
