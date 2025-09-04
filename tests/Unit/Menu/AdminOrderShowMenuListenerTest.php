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

namespace Tests\Sylius\AdyenPlugin\Unit\Menu;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Menu\AdminOrderShowMenuListener;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\Bundle\AdminBundle\Event\OrderShowMenuBuilderEvent;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class AdminOrderShowMenuListenerTest extends TestCase
{
    private AdminOrderShowMenuListener $listener;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private MockObject|StateMachineInterface $stateMachine;

    private MockObject|OrderShowMenuBuilderEvent $event;

    private ItemInterface|MockObject $menu;

    private MockObject|OrderInterface $order;

    private MockObject|PaymentInterface $payment;

    protected function setUp(): void
    {
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->listener = new AdminOrderShowMenuListener(
            $this->adyenPaymentMethodChecker,
            $this->stateMachine,
        );

        $this->event = $this->createMock(OrderShowMenuBuilderEvent::class);
        $this->menu = $this->createMock(ItemInterface::class);
        $this->order = $this->createMock(OrderInterface::class);
        $this->payment = $this->createMock(PaymentInterface::class);
    }

    public function testDoesNotAddRefundButtonWhenOrderIsNotFulfilled(): void
    {
        $this->event->expects($this->once())->method('getMenu')->willReturn($this->menu);
        $this->event->expects($this->once())->method('getOrder')->willReturn($this->order);

        $this->order->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_NEW);

        $this->order->expects($this->never())->method('getLastPayment');
        $this->menu->expects($this->never())->method('addChild');

        $this->listener->addRefundButton($this->event);
    }

    public function testDoesNotAddRefundButtonWhenNoPaymentExists(): void
    {
        $this->event->expects($this->once())->method('getMenu')->willReturn($this->menu);
        $this->event->expects($this->once())->method('getOrder')->willReturn($this->order);

        $this->order->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED);

        $this->order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn(null);

        $this->menu->expects($this->never())->method('addChild');

        $this->listener->addRefundButton($this->event);
    }

    public function testDoesNotAddRefundButtonWhenPaymentIsNotAdyen(): void
    {
        $this->event->expects($this->once())->method('getMenu')->willReturn($this->menu);
        $this->event->expects($this->once())->method('getOrder')->willReturn($this->order);

        $this->order->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED);

        $this->order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($this->payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($this->payment)
            ->willReturn(false);

        $this->stateMachine->expects($this->never())->method('can');
        $this->menu->expects($this->never())->method('addChild');

        $this->listener->addRefundButton($this->event);
    }

    public function testDoesNotAddRefundButtonWhenReverseTransitionIsNotAllowed(): void
    {
        $this->event->expects($this->once())->method('getMenu')->willReturn($this->menu);
        $this->event->expects($this->once())->method('getOrder')->willReturn($this->order);

        $this->order->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED);

        $this->order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($this->payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($this->payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isCaptureMode')
            ->with($this->payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with($this->payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REVERSE)
            ->willReturn(false);

        $this->menu->expects($this->never())->method('addChild');

        $this->listener->addRefundButton($this->event);
    }

    public function testDoesNotAddRefundButtonWhenPaymentIsNotInAutomaticCaptureMode(): void
    {
        $this->event->expects($this->once())->method('getMenu')->willReturn($this->menu);
        $this->event->expects($this->once())->method('getOrder')->willReturn($this->order);

        $this->order->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED);

        $this->order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($this->payment);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($this->payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isCaptureMode')
            ->with($this->payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(false);

        $this->stateMachine->expects($this->never())->method('can');
        $this->menu->expects($this->never())->method('addChild');

        $this->listener->addRefundButton($this->event);
    }

    public function testAddsRefundButtonSuccessfully(): void
    {
        $orderId = 123;
        $paymentId = 456;
        $menuItem = $this->createMock(ItemInterface::class);

        $this->event->expects($this->once())->method('getMenu')->willReturn($this->menu);
        $this->event->expects($this->once())->method('getOrder')->willReturn($this->order);

        $this->order->expects($this->once())
            ->method('getState')
            ->willReturn(OrderInterface::STATE_FULFILLED);

        $this->order->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($this->payment);

        $this->order->expects($this->once())
            ->method('getId')
            ->willReturn($orderId);

        $this->payment->expects($this->once())
            ->method('getId')
            ->willReturn($paymentId);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($this->payment)
            ->willReturn(true);

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isCaptureMode')
            ->with($this->payment, PaymentCaptureMode::AUTOMATIC)
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with($this->payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_REVERSE)
            ->willReturn(true);

        $this->menu->expects($this->once())
            ->method('addChild')
            ->with('reverse_payment', [
                'route' => 'sylius_adyen_admin_order_payment_reverse',
                'routeParameters' => [
                    'id' => $orderId,
                    'paymentId' => $paymentId,
                ],
            ])
            ->willReturn($menuItem);

        $menuItem->expects($this->once())
            ->method('setLabel')
            ->with('sylius.ui.refund')
            ->willReturn($menuItem);

        $menuItem->expects($this->exactly(2))
            ->method('setLabelAttribute')
            ->willReturnCallback(function ($key, $value) use ($menuItem) {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertEquals('icon', $key);
                    self::assertEquals('reply', $value);
                } elseif ($callCount === 2) {
                    self::assertEquals('color', $key);
                    self::assertEquals('purple', $value);
                }

                return $menuItem;
            });

        $this->listener->addRefundButton($this->event);
    }

    #[DataProvider('provideNonFulfilledOrderStates')]
    public function testDoesNotAddRefundButtonForNonFulfilledStates(string $state): void
    {
        $this->event->expects($this->once())->method('getMenu')->willReturn($this->menu);
        $this->event->expects($this->once())->method('getOrder')->willReturn($this->order);

        $this->order->expects($this->once())
            ->method('getState')
            ->willReturn($state);

        $this->order->expects($this->never())->method('getLastPayment');
        $this->menu->expects($this->never())->method('addChild');

        $this->listener->addRefundButton($this->event);
    }

    public static function provideNonFulfilledOrderStates(): iterable
    {
        yield 'cart state' => [OrderInterface::STATE_CART];
        yield 'new state' => [OrderInterface::STATE_NEW];
        yield 'cancelled state' => [OrderInterface::STATE_CANCELLED];
    }
}
