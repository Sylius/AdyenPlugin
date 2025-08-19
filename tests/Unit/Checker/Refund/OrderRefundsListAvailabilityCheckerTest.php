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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker\Refund;

use Payum\Core\Model\GatewayConfigInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\Refund\OrderRefundsListAvailabilityChecker;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\RefundPlugin\Checker\OrderRefundingAvailabilityCheckerInterface;

final class OrderRefundsListAvailabilityCheckerTest extends TestCase
{
    private OrderRefundsListAvailabilityChecker $checker;

    private MockObject|OrderRefundingAvailabilityCheckerInterface $decoratedChecker;

    private MockObject|OrderRepositoryInterface $orderRepository;

    protected function setUp(): void
    {
        $this->decoratedChecker = $this->createMock(OrderRefundingAvailabilityCheckerInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);

        $this->checker = new OrderRefundsListAvailabilityChecker(
            $this->decoratedChecker,
            $this->orderRepository,
        );
    }

    public function testThrowsInvalidArgumentExceptionWhenOrderIsNull(): void
    {
        $orderNumber = 'ORDER-123';

        $this->orderRepository
            ->expects($this->once())
            ->method('findOneByNumber')
            ->with($orderNumber)
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Order with number "%s" does not exist.', $orderNumber));

        ($this->checker)($orderNumber);
    }

    #[DataProvider('delegationScenariosProvider')]
    public function testDelegatesToDecoratedChecker(
        bool $hasPayment,
        bool $hasMethod,
        bool $hasGatewayConfig,
        ?array $config,
        ?string $factoryName,
    ): void {
        $orderNumber = 'ORDER-123';
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $this->orderRepository
            ->expects($this->once())
            ->method('findOneByNumber')
            ->with($orderNumber)
            ->willReturn($order);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($hasPayment ? $payment : null);

        if ($hasPayment) {
            $payment
                ->expects($this->once())
                ->method('getMethod')
                ->willReturn($hasMethod ? $paymentMethod : null);
        }

        if ($hasMethod) {
            $paymentMethod
                ->expects($this->once())
                ->method('getGatewayConfig')
                ->willReturn($hasGatewayConfig ? $gatewayConfig : null);
        }

        if ($hasGatewayConfig) {
            $gatewayConfig
                ->expects($this->once())
                ->method('getConfig')
                ->willReturn($config);

            if ($factoryName !== null) {
                $gatewayConfig
                    ->expects($this->once())
                    ->method('getFactoryName')
                    ->willReturn($factoryName);
            }
        }

        $this->decoratedChecker
            ->expects($this->once())
            ->method('__invoke')
            ->with($orderNumber)
            ->willReturn(true);

        $result = ($this->checker)($orderNumber);

        self::assertTrue($result);
    }

    public static function delegationScenariosProvider(): \Generator
    {
        yield 'no completed payment' => [
            'hasPayment' => false,
            'hasMethod' => false,
            'hasGatewayConfig' => false,
            'config' => null,
            'factoryName' => null,
        ];

        yield 'payment has no method' => [
            'hasPayment' => true,
            'hasMethod' => false,
            'hasGatewayConfig' => false,
            'config' => null,
            'factoryName' => null,
        ];

        yield 'payment method has no gateway config' => [
            'hasPayment' => true,
            'hasMethod' => true,
            'hasGatewayConfig' => false,
            'config' => null,
            'factoryName' => null,
        ];

        yield 'non-Adyen payment via config' => [
            'hasPayment' => true,
            'hasMethod' => true,
            'hasGatewayConfig' => true,
            'config' => ['factory_name' => 'stripe'],
            'factoryName' => null,
        ];

        yield 'non-Adyen payment via factory name' => [
            'hasPayment' => true,
            'hasMethod' => true,
            'hasGatewayConfig' => true,
            'config' => [],
            'factoryName' => 'paypal',
        ];
    }

    #[DataProvider('adyenPaymentStatesProvider')]
    public function testReturnsFalseForAdyenPaymentsInSpecificStates(
        string $paymentState,
    ): void {
        $orderNumber = 'ORDER-123';
        $order = $this->createMock(OrderInterface::class);
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);

        $this->orderRepository
            ->expects($this->once())
            ->method('findOneByNumber')
            ->with($orderNumber)
            ->willReturn($order);

        $order
            ->expects($this->once())
            ->method('getLastPayment')
            ->willReturn($payment);

        $payment
            ->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $payment
            ->expects($this->once())
            ->method('getState')
            ->willReturn($paymentState);

        $paymentMethod
            ->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn(['factory_name' => AdyenClientProviderInterface::FACTORY_NAME]);

        $this->decoratedChecker
            ->expects($this->never())
            ->method('__invoke');

        $result = ($this->checker)($orderNumber);

        self::assertFalse($result);
    }

    public static function adyenPaymentStatesProvider(): \Generator
    {
        yield 'Adyen payment in processing reversal state' => [
            'paymentState' => PaymentGraph::STATE_PROCESSING_REVERSAL,
        ];

        yield 'Adyen payment in completed state' => [
            'paymentState' => PaymentInterface::STATE_COMPLETED,
        ];

        yield 'Adyen payment in cancelled state' => [
            'paymentState' => PaymentInterface::STATE_CANCELLED,
        ];

        yield 'Adyen payment in refunded state' => [
            'paymentState' => PaymentInterface::STATE_REFUNDED,
        ];
    }
}
