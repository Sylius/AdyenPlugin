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

namespace Tests\Sylius\AdyenPlugin\Unit\Bus\Handler;

use Payum\Core\Model\GatewayConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Bus\Command\AlterPaymentCommand;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\AdyenPlugin\Bus\Command\RequestCapture;
use Sylius\AdyenPlugin\Bus\Handler\AlterPaymentHandler;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProvider;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;

class AlterPaymentHandlerTest extends TestCase
{
    private const PSP_REFERENCE = 'Szczebrzeszyn';

    private const ORDER_CURRENCY_CODE = 'EUR';

    private const ORDER_AMOUNT = 42;

    use AdyenClientTrait;

    private AlterPaymentHandler $handler;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    protected function setUp(): void
    {
        $this->setupAdyenClientMocks();
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->handler = new AlterPaymentHandler($this->adyenClientProvider, $this->adyenPaymentMethodChecker);
    }

    #[DataProvider('provideForTestForNonApplicablePayment')]
    public function testForNonApplicablePayment(?PaymentInterface $payment = null, ?string $orderPaymentState = null): void
    {
        $this->adyenClientProvider
            ->expects($this->never())
            ->method('getForPaymentMethod')
        ;

        $order = new Order();
        if (null !== $orderPaymentState) {
            $order->setPaymentState($orderPaymentState);
        }

        if (null !== $payment) {
            $order->addPayment($payment);
        }

        $shouldCallChecker =
            null !== $payment &&
            $payment->getState() === PaymentInterface::STATE_AUTHORIZED &&
            $orderPaymentState !== PaymentInterface::STATE_COMPLETED
        ;

        if ($shouldCallChecker) {
            $this->adyenPaymentMethodChecker->expects($this->once())
                ->method('isAdyenPayment')
                ->with($payment)
                ->willReturn(false);
        } else {
            $this->adyenPaymentMethodChecker->expects($this->never())
                ->method('isAdyenPayment');
        }

        $command = $this->createMock(AlterPaymentCommand::class);
        $command
            ->method('getOrder')
            ->willReturn($order)
        ;

        ($this->handler)($command);
    }

    public static function provideForTestForNonApplicablePayment(): array
    {
        $paymentWithoutMethod = new Payment();

        $methodWithEmptyConfig = new PaymentMethod();
        $paymentWithEmptyConfig = new Payment();
        $paymentWithEmptyConfig->setMethod($methodWithEmptyConfig);

        $config = new GatewayConfig();
        $config->setConfig(['blah' => 1]);
        $nonAdyenPaymentMethod = new PaymentMethod();
        $nonAdyenPaymentMethod->setGatewayConfig($config);
        $paymentWithoutAdyenConfiguration = new Payment();
        $paymentWithoutAdyenConfiguration->setState(PaymentInterface::STATE_AUTHORIZED);
        $paymentWithoutAdyenConfiguration->setMethod($nonAdyenPaymentMethod);

        $paymentWithoutReference = clone $paymentWithoutAdyenConfiguration;
        $paymentWithoutReference->getMethod()->getGatewayConfig()->setFactoryName(AdyenClientProvider::FACTORY_NAME);
        $paymentWithoutReference->getMethod()->getGatewayConfig()->setConfig([
            AdyenClientProvider::FACTORY_NAME => 1,
        ]);

        // Create a non-Adyen authorized payment that should trigger the checker
        $authorizedNonAdyenPayment = new Payment();
        $authorizedNonAdyenPayment->setState(PaymentInterface::STATE_AUTHORIZED);
        $authorizedNonAdyenPayment->setMethod($nonAdyenPaymentMethod);

        return [
            'payment not found' => [null],
            'payment without method' => [$paymentWithoutMethod],
            'payment method without configuration' => [$paymentWithEmptyConfig],
            'completed order' => [$paymentWithoutAdyenConfiguration, PaymentInterface::STATE_COMPLETED],
            'authorized non-Adyen payment' => [$authorizedNonAdyenPayment],
        ];
    }

    #[DataProvider('provideForTestForValidPayment')]
    public function testForValidPayment(string $commandClass, callable $setupMocker): void
    {
        $config = new GatewayConfig();
        $config->setFactoryName(AdyenClientProvider::FACTORY_NAME);
        $config->setConfig([
            AdyenClientProvider::FACTORY_NAME => 1,
        ]);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($config);

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);
        $payment->setMethod($paymentMethod);
        $payment->setDetails(['pspReference' => self::PSP_REFERENCE]);

        $order = new Order();
        $order->addPayment($payment);
        $order->setCurrencyCode(self::ORDER_CURRENCY_CODE);

        $item = new OrderItem();
        $item->setUnitPrice(self::ORDER_AMOUNT);
        $item->setOrder($order);
        $item->addUnit(new OrderItemUnit($item));

        $this->adyenPaymentMethodChecker->expects($this->once())
            ->method('isAdyenPayment')
            ->with($payment)
            ->willReturn(true);

        $setupMocker->bindTo($this)();

        /**
         * @var AlterPaymentCommand $command
         */
        $command = new $commandClass($order);

        ($this->handler)($command);
    }

    public static function provideForTestForValidPayment(): array
    {
        return [
            'request capture' => [
                RequestCapture::class,
                function () {
                    $this
                        ->adyenClient
                        ->expects($this->once())
                        ->method('requestCapture')
                        ->with(
                            $this->isInstanceOf(PaymentInterface::class),
                        )
                    ;
                },
            ],
            'cancel payment' => [
                CancelPayment::class,
                function () {
                    $this
                        ->adyenClient
                        ->expects($this->once())
                        ->method('requestCancellation')
                        ->with(
                            $this->isInstanceOf(PaymentInterface::class),
                        )
                    ;
                },
            ],
        ];
    }
}
