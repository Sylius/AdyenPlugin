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

namespace Tests\Sylius\AdyenPlugin\Unit\Processor\Payment;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Processor\Payment\AuthorizationStateProcessor;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class AuthorizationStateProcessorTest extends TestCase
{
    private MockObject|StateMachineInterface $stateMachine;

    private EntityManagerInterface|MockObject $entityManager;

    private AuthorizationStateProcessor $processor;

    protected function setUp(): void
    {
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->processor = new AuthorizationStateProcessor(
            $this->stateMachine,
            $this->entityManager,
        );
    }

    #[DataProvider('provideEarlyReturnCases')]
    public function testProcessReturnsEarlyWhen(
        mixed $paymentMethod,
        ?GatewayConfig $gatewayConfig,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);

        if ($paymentMethod === 'mock') {
            $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        }

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        if ($paymentMethod !== null) {
            $paymentMethod->expects($this->once())
                ->method('getGatewayConfig')
                ->willReturn($gatewayConfig);
        }

        $this->stateMachine->expects($this->never())
            ->method('can');

        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->processor->process($payment);
    }

    public static function provideEarlyReturnCases(): iterable
    {
        yield 'payment method is null' => [
            'paymentMethod' => null,
            'gatewayConfig' => null,
        ];

        yield 'gateway config is null' => [
            'paymentMethod' => 'mock',
            'gatewayConfig' => null,
        ];
    }

    #[DataProvider('provideNonAdyenGatewayFactoryNames')]
    public function testProcessReturnsEarlyForNonAdyenGateway(string $factoryName): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfig::class);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getFactoryName')
            ->willReturn($factoryName);

        $this->stateMachine->expects($this->never())
            ->method('can');

        $this->stateMachine->expects($this->never())
            ->method('apply');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->processor->process($payment);
    }

    public static function provideNonAdyenGatewayFactoryNames(): iterable
    {
        yield 'PayPal gateway' => ['paypal'];
        yield 'Stripe gateway' => ['stripe'];
        yield 'Offline gateway' => ['offline'];
        yield 'Bank transfer gateway' => ['bank_transfer'];
        yield 'Empty factory name' => [''];
    }

    #[DataProvider('provideStateMachineTransitionScenarios')]
    public function testProcessHandlesStateMachineTransitions(
        bool $canTransition,
        bool $shouldApplyTransition,
        bool $shouldFlush,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfig::class);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $paymentMethod->expects($this->once())
            ->method('getGatewayConfig')
            ->willReturn($gatewayConfig);

        $gatewayConfig->expects($this->once())
            ->method('getFactoryName')
            ->willReturn(AdyenClientProviderInterface::FACTORY_NAME);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with(
                $payment,
                PaymentGraph::GRAPH,
                PaymentGraph::TRANSITION_CAPTURE,
            )
            ->willReturn($canTransition);

        if ($shouldApplyTransition) {
            $this->stateMachine->expects($this->once())
                ->method('apply')
                ->with(
                    $payment,
                    PaymentGraph::GRAPH,
                    PaymentGraph::TRANSITION_CAPTURE,
                );
        } else {
            $this->stateMachine->expects($this->never())
                ->method('apply');
        }

        if ($shouldFlush) {
            $this->entityManager->expects($this->once())
                ->method('flush');
        } else {
            $this->entityManager->expects($this->never())
                ->method('flush');
        }

        $this->processor->process($payment);
    }

    public static function provideStateMachineTransitionScenarios(): iterable
    {
        yield 'can transition to capture' => [
            'canTransition' => true,
            'shouldApplyTransition' => true,
            'shouldFlush' => true,
        ];

        yield 'cannot transition to capture' => [
            'canTransition' => false,
            'shouldApplyTransition' => false,
            'shouldFlush' => false,
        ];
    }

    #[DataProvider('provideCompleteFlowScenarios')]
    public function testCompleteFlowScenarios(
        mixed $paymentMethod,
        mixed $gatewayConfig,
        ?string $factoryName,
        bool $canTransition,
        int $getMethodCalls,
        int $getGatewayConfigCalls,
        int $getFactoryNameCalls,
        int $canCalls,
        int $applyCalls,
        int $flushCalls,
    ): void {
        $payment = $this->createMock(PaymentInterface::class);

        if ($paymentMethod === 'mock') {
            $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        }

        if ($gatewayConfig === 'mock') {
            $gatewayConfig = $this->createMock(GatewayConfig::class);
        }

        $payment->expects($this->exactly($getMethodCalls))
            ->method('getMethod')
            ->willReturn($paymentMethod);

        if ($paymentMethod !== null && $getGatewayConfigCalls > 0) {
            $paymentMethod->expects($this->exactly($getGatewayConfigCalls))
                ->method('getGatewayConfig')
                ->willReturn($gatewayConfig);
        }

        if ($gatewayConfig !== null && $getFactoryNameCalls > 0) {
            $gatewayConfig->expects($this->exactly($getFactoryNameCalls))
                ->method('getFactoryName')
                ->willReturn($factoryName);
        }

        if ($canCalls > 0) {
            $this->stateMachine->expects($this->exactly($canCalls))
                ->method('can')
                ->with(
                    $payment,
                    PaymentGraph::GRAPH,
                    PaymentGraph::TRANSITION_CAPTURE,
                )
                ->willReturn($canTransition);
        } else {
            $this->stateMachine->expects($this->never())
                ->method('can');
        }

        if ($applyCalls > 0) {
            $this->stateMachine->expects($this->exactly($applyCalls))
                ->method('apply')
                ->with(
                    $payment,
                    PaymentGraph::GRAPH,
                    PaymentGraph::TRANSITION_CAPTURE,
                );
        } else {
            $this->stateMachine->expects($this->never())
                ->method('apply');
        }

        if ($flushCalls > 0) {
            $this->entityManager->expects($this->exactly($flushCalls))
                ->method('flush');
        } else {
            $this->entityManager->expects($this->never())
                ->method('flush');
        }

        $this->processor->process($payment);
    }

    public static function provideCompleteFlowScenarios(): iterable
    {
        yield 'null payment method stops processing' => [
            'paymentMethod' => null,
            'gatewayConfig' => null,
            'factoryName' => null,
            'canTransition' => false,
            'getMethodCalls' => 1,
            'getGatewayConfigCalls' => 0,
            'getFactoryNameCalls' => 0,
            'canCalls' => 0,
            'applyCalls' => 0,
            'flushCalls' => 0,
        ];

        yield 'null gateway config stops processing' => [
            'paymentMethod' => 'mock',
            'gatewayConfig' => null,
            'factoryName' => null,
            'canTransition' => false,
            'getMethodCalls' => 1,
            'getGatewayConfigCalls' => 1,
            'getFactoryNameCalls' => 0,
            'canCalls' => 0,
            'applyCalls' => 0,
            'flushCalls' => 0,
        ];

        yield 'non-adyen gateway stops processing' => [
            'paymentMethod' => 'mock',
            'gatewayConfig' => 'mock',
            'factoryName' => 'stripe',
            'canTransition' => false,
            'getMethodCalls' => 1,
            'getGatewayConfigCalls' => 1,
            'getFactoryNameCalls' => 1,
            'canCalls' => 0,
            'applyCalls' => 0,
            'flushCalls' => 0,
        ];

        yield 'adyen gateway but cannot transition' => [
            'paymentMethod' => 'mock',
            'gatewayConfig' => 'mock',
            'factoryName' => AdyenClientProviderInterface::FACTORY_NAME,
            'canTransition' => false,
            'getMethodCalls' => 1,
            'getGatewayConfigCalls' => 1,
            'getFactoryNameCalls' => 1,
            'canCalls' => 1,
            'applyCalls' => 0,
            'flushCalls' => 0,
        ];

        yield 'successful adyen payment capture' => [
            'paymentMethod' => 'mock',
            'gatewayConfig' => 'mock',
            'factoryName' => AdyenClientProviderInterface::FACTORY_NAME,
            'canTransition' => true,
            'getMethodCalls' => 1,
            'getGatewayConfigCalls' => 1,
            'getFactoryNameCalls' => 1,
            'canCalls' => 1,
            'applyCalls' => 1,
            'flushCalls' => 1,
        ];
    }

    public function testProcessWithConcretePaymentAndPaymentMethod(): void
    {
        $payment = new Payment();
        $paymentMethod = new PaymentMethod();
        $gatewayConfig = new GatewayConfig();

        $gatewayConfig->setFactoryName(AdyenClientProviderInterface::FACTORY_NAME);
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $payment->setMethod($paymentMethod);

        $this->stateMachine->expects($this->once())
            ->method('can')
            ->with(
                $payment,
                PaymentGraph::GRAPH,
                PaymentGraph::TRANSITION_CAPTURE,
            )
            ->willReturn(true);

        $this->stateMachine->expects($this->once())
            ->method('apply')
            ->with(
                $payment,
                PaymentGraph::GRAPH,
                PaymentGraph::TRANSITION_CAPTURE,
            );

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->processor->process($payment);
    }
}
