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

namespace Tests\Sylius\AdyenPlugin\Unit\Resolver\ExpressCheckout;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\OrderCheckoutCompleteIntegrityCheckerInterface;
use Sylius\AdyenPlugin\Repository\Query\AdyenPaymentMethodQueryInterface;
use Sylius\AdyenPlugin\Resolver\ExpressCheckout\CheckoutResolver;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;

final class CheckoutResolverTest extends TestCase
{
    private MockObject&ObjectManager $orderManager;

    private MockObject&StateMachineInterface $stateMachine;

    private AdyenPaymentMethodQueryInterface&MockObject $adyenPaymentMethodQuery;

    private MockObject&OrderCheckoutCompleteIntegrityCheckerInterface $integrityChecker;

    protected function setUp(): void
    {
        $this->orderManager = $this->createMock(ObjectManager::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->adyenPaymentMethodQuery = $this->createMock(AdyenPaymentMethodQueryInterface::class);
        $this->integrityChecker = $this->createMock(OrderCheckoutCompleteIntegrityCheckerInterface::class);
    }

    public function testItResolvesCheckoutWithShippingRequired(): void
    {
        $resolver = new CheckoutResolver(
            $this->orderManager,
            $this->stateMachine,
            $this->adyenPaymentMethodQuery,
            $this->integrityChecker,
        );

        $order = $this->createMock(OrderInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $order->method('getChannel')->willReturn($channel);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_CART)->willReturn($payment);

        $this->adyenPaymentMethodQuery
            ->expects(self::once())
            ->method('findOneAdyenByChannel')
            ->with($channel)
            ->willReturn($paymentMethod)
        ;

        $payment->expects(self::once())->method('setMethod')->with($paymentMethod);

        $this->stateMachine
            ->expects(self::exactly(2))
            ->method('can')
            ->willReturnCallback(function (OrderInterface $order, string $graph, string $transition) {
                return
                    $transition === OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING ||
                    $transition === OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT
                ;
            });

        $this->stateMachine
            ->expects(self::exactly(3))
            ->method('apply')
            ->willReturnCallback(function (OrderInterface $order, string $graph, string $transition) {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_ADDRESS, $transition);
                } elseif ($callCount === 2) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING, $transition);
                } elseif ($callCount === 3) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT, $transition);
                }
            });

        $this->integrityChecker->expects(self::once())->method('check')->with($order);
        $this->orderManager->expects(self::once())->method('flush');

        $resolver->resolve($order);
    }

    public function testItSkipsSelectShippingWhenNotRequired(): void
    {
        $resolver = new CheckoutResolver(
            $this->orderManager,
            $this->stateMachine,
            $this->adyenPaymentMethodQuery,
            $this->integrityChecker,
        );

        $order = $this->createMock(OrderInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $order->method('getChannel')->willReturn($channel);
        $order->method('isShippingRequired')->willReturn(false);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_CART)->willReturn($payment);

        $this->adyenPaymentMethodQuery
            ->expects(self::once())
            ->method('findOneAdyenByChannel')
            ->with($channel)
            ->willReturn($paymentMethod)
        ;

        $payment->expects(self::once())->method('setMethod')->with($paymentMethod);

        $this->stateMachine
            ->expects(self::once())
            ->method('can')
            ->with($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT)
            ->willReturn(true)
        ;

        $this->stateMachine
            ->expects(self::exactly(2))
            ->method('apply')
            ->willReturnCallback(function (OrderInterface $order, string $graph, string $transition) {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_ADDRESS, $transition);
                } elseif ($callCount === 2) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT, $transition);
                }
            });

        $this->integrityChecker->expects(self::once())->method('check')->with($order);
        $this->orderManager->expects(self::once())->method('flush');

        $resolver->resolve($order);
    }

    public function testItSkipsSelectShippingWhenTransitionNotAvailable(): void
    {
        $resolver = new CheckoutResolver(
            $this->orderManager,
            $this->stateMachine,
            $this->adyenPaymentMethodQuery,
            $this->integrityChecker,
        );

        $order = $this->createMock(OrderInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $order->method('getChannel')->willReturn($channel);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_CART)->willReturn($payment);

        $this->adyenPaymentMethodQuery
            ->expects(self::once())
            ->method('findOneAdyenByChannel')
            ->with($channel)
            ->willReturn($paymentMethod)
        ;

        $payment->expects(self::once())->method('setMethod')->with($paymentMethod);

        $this->stateMachine
            ->expects(self::exactly(2))
            ->method('can')
            ->willReturnCallback(function (OrderInterface $order, string $graph, string $transition) {
                if ($transition === OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING) {
                    return false;
                }

                return $transition === OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT;
            });

        $this->stateMachine
            ->expects(self::exactly(2))
            ->method('apply')
            ->willReturnCallback(function (OrderInterface $order, string $graph, string $transition) {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_ADDRESS, $transition);
                } elseif ($callCount === 2) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT, $transition);
                }
            });

        $this->integrityChecker->expects(self::once())->method('check')->with($order);
        $this->orderManager->expects(self::once())->method('flush');

        $resolver->resolve($order);
    }

    public function testItSkipsSelectPaymentWhenTransitionNotAvailable(): void
    {
        $resolver = new CheckoutResolver(
            $this->orderManager,
            $this->stateMachine,
            $this->adyenPaymentMethodQuery,
            $this->integrityChecker,
        );

        $order = $this->createMock(OrderInterface::class);
        $channel = $this->createMock(ChannelInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $payment = $this->createMock(PaymentInterface::class);

        $order->method('getChannel')->willReturn($channel);
        $order->method('isShippingRequired')->willReturn(true);
        $order->method('getLastPayment')->with(PaymentInterface::STATE_CART)->willReturn($payment);

        $this->adyenPaymentMethodQuery
            ->expects(self::once())
            ->method('findOneAdyenByChannel')
            ->with($channel)
            ->willReturn($paymentMethod)
        ;

        $payment->expects(self::once())->method('setMethod')->with($paymentMethod);

        $this->stateMachine
            ->expects(self::exactly(2))
            ->method('can')
            ->willReturnCallback(function (OrderInterface $order, string $graph, string $transition) {
                if ($transition === OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT) {
                    return false;
                }

                return $transition === OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING;
            });

        $this->stateMachine
            ->expects(self::exactly(2))
            ->method('apply')
            ->willReturnCallback(function (OrderInterface $order, string $graph, string $transition) {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_ADDRESS, $transition);
                } elseif ($callCount === 2) {
                    self::assertSame(OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING, $transition);
                }
            });

        $this->integrityChecker->expects(self::once())->method('check')->with($order);
        $this->orderManager->expects(self::once())->method('flush');

        $resolver->resolve($order);
    }
}
