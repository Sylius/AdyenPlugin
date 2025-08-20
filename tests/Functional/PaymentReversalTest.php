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

namespace Tests\Sylius\AdyenPlugin\Functional;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\AdyenPlugin\Client\ResponseStatus;
use Sylius\AdyenPlugin\Controller\Shop\PaymentsAction;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\Amount;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Order\OrderTransitions;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Sylius\AdyenPlugin\Functional\Stub\AdyenClientStub;

final class PaymentReversalTest extends WebTestCase
{
    private AdyenClientStub $adyenClientStub;

    private PaymentsAction $paymentsAction;

    private OrderInterface $testOrder;

    private static PaymentMethod $sharedPaymentMethod;

    private MessageBusInterface $messageBus;

    private PaymentCommandFactoryInterface $paymentCommandFactory;

    private StateMachineInterface $stateMachine;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$sharedPaymentMethod = new PaymentMethod();
        self::$sharedPaymentMethod->setCode('adyen_checkout');
        self::$sharedPaymentMethod->setCurrentLocale('en_US');
        self::$sharedPaymentMethod->setFallbackLocale('en_US');
        self::$sharedPaymentMethod->setName('Adyen Checkout');

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName(AdyenClientProviderInterface::FACTORY_NAME);
        $gatewayConfig->setGatewayName('adyen_checkout');
        $gatewayConfig->setConfig([
            'environment' => 'test',
            'merchant_account' => 'test_merchant',
            'api_key' => 'test_api_key',
            'client_key' => 'test_client_key',
            'hmac_key' => 'test_hmac_key',
        ]);

        self::$sharedPaymentMethod->setGatewayConfig($gatewayConfig);
    }

    private function purgeDatabase(): void
    {
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $purger = new ORMPurger($entityManager);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
        $purger->purge();

        $entityManager->clear();
    }

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);

        $container = self::getContainer();

        $this->purgeDatabase();
        $this->testOrder = $this->createTestOrder();

        $paymentMethodRepository = $container->get('sylius.repository.payment_method');
        $paymentMethodRepository->add(self::$sharedPaymentMethod);

        $customerRepository = $container->get('sylius.repository.customer');
        $customerRepository->add($this->testOrder->getCustomer());

        $this->testOrder->getLastPayment()->setMethod(self::$sharedPaymentMethod);

        $orderRepository = $container->get('sylius.repository.order');
        $orderRepository->add($this->testOrder);

        $this->adyenClientStub = $container->get('sylius_adyen.test.adyen_client_stub');

        $testCartContext = $container->get('sylius_adyen.test.cart_context');
        $testCartContext->setOrder($this->testOrder);

        $this->paymentsAction = $container->get('sylius_adyen.controller.shop.payments');
        $this->messageBus = $container->get('sylius.command_bus');
        $this->paymentCommandFactory = $container->get('sylius_adyen.bus.payment_command_factory');
        $this->stateMachine = $container->get('sylius_abstraction.state_machine');
    }

    protected function tearDown(): void
    {
        $this->purgeDatabase();

        parent::tearDown();
    }

    public function testReversalNotInitiatedForNonAdyenPayment(): void
    {
        $nonAdyenPaymentMethod = new PaymentMethod();
        $nonAdyenPaymentMethod->setCode('bank_transfer');
        $nonAdyenPaymentMethod->setCurrentLocale('en_US');
        $nonAdyenPaymentMethod->setFallbackLocale('en_US');
        $nonAdyenPaymentMethod->setName('Bank Transfer');

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('offline');
        $gatewayConfig->setGatewayName('bank_transfer');
        $gatewayConfig->setConfig([]);

        $nonAdyenPaymentMethod->setGatewayConfig($gatewayConfig);

        $paymentMethodRepository = self::getContainer()->get('sylius.repository.payment_method');
        $paymentMethodRepository->add($nonAdyenPaymentMethod);

        $order = $this->createTestOrder();
        $order->setState(OrderInterface::STATE_NEW);
        $order->setPaymentState(OrderPaymentStates::STATE_AUTHORIZED);
        $payment = $order->getLastPayment();
        $payment->setMethod($nonAdyenPaymentMethod);
        $payment->setState(PaymentInterface::STATE_AUTHORIZED);

        $orderRepository = self::getContainer()->get('sylius.repository.order');
        $orderRepository->add($order);

        $this->stateMachine->apply($order, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        self::assertEquals(OrderInterface::STATE_CANCELLED, $order->getState());
        self::assertEquals(PaymentInterface::STATE_CANCELLED, $payment->getState());

        $reversalRequest = $this->adyenClientStub->getLastReversalRequest();
        self::assertNull($reversalRequest);
    }

    public function testPaymentStateChangesToProcessingReversalOnCancelTransition(): void
    {
        $this->setupPaidOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        $initialDetails = $payment->getDetails();

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());
        self::assertArrayHasKey('pspReference', $initialDetails);
        self::assertEquals('TEST_PSP_REF_123', $initialDetails['pspReference']);

        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_999',
            'status' => ResponseStatus::RECEIVED,
        ]);

        $this->stateMachine->apply($this->testOrder, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());
    }

    public function testCancellationWebhookAfterReversalInitiated(): void
    {
        $this->setupPaidOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());

        // Set up the reversal response
        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_456',
            'status' => ResponseStatus::RECEIVED,
        ]);

        // Initiate order cancellation which will trigger reversal
        $this->stateMachine->apply($this->testOrder, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();

        // Verify reversal was initiated
        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());

        // Simulate cancellation webhook from Adyen
        $this->simulateWebhook($payment, 'cancellation');
        $entityManager->flush();

        // Verify final states after webhook
        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(PaymentInterface::STATE_CANCELLED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_CANCELLED, $this->testOrder->getPaymentState());
    }

    public function testRefundWebhookAfterReversalInitiated(): void
    {
        $this->setupPaidOrderWithAdyenPayment();

        $payment = $this->testOrder->getLastPayment();
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());

        // Set up the reversal response
        $this->adyenClientStub->setReversalResponse([
            'paymentPspReference' => 'TEST_PSP_REF_123',
            'pspReference' => 'REVERSAL_PSP_REF_456',
            'status' => ResponseStatus::RECEIVED,
        ]);

        // Initiate order cancellation which will trigger reversal
        $this->stateMachine->apply($this->testOrder, OrderTransitions::GRAPH, OrderTransitions::TRANSITION_CANCEL);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();

        // Verify reversal was initiated
        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(PaymentGraph::STATE_PROCESSING_REVERSAL, $payment->getState());

        // Simulate refund webhook from Adyen
        $this->simulateWebhook($payment, 'refund');
        $entityManager->flush();

        // Verify final states after refund webhook
        self::assertEquals(OrderInterface::STATE_CANCELLED, $this->testOrder->getState());
        self::assertEquals(PaymentInterface::STATE_REFUNDED, $payment->getState());
        self::assertEquals(OrderPaymentStates::STATE_REFUNDED, $this->testOrder->getPaymentState());

        // Verify PaymentRefund entity was created
        $refundPaymentRepository = self::getContainer()->get('sylius_refund.repository.refund_payment');
        $refundPayments = $refundPaymentRepository->findBy(['order' => $this->testOrder]);
        self::assertCount(1, $refundPayments);

        /** @var RefundPaymentInterface $refundPayment */
        $refundPayment = $refundPayments[0];
        self::assertEquals($this->testOrder->getNumber(), $refundPayment->getOrderNumber());
        self::assertEquals($payment->getAmount(), $refundPayment->getAmount());
        self::assertEquals($payment->getCurrencyCode(), $refundPayment->getCurrencyCode());
        self::assertEquals($payment->getMethod(), $refundPayment->getPaymentMethod());

        // Verify AdyenReference entity was created and linked
        $adyenReferenceRepository = self::getContainer()->get('sylius_adyen.repository.adyen_reference');
        /** @var AdyenReferenceRepositoryInterface $adyenReferenceRepository */
        $adyenReferences = $adyenReferenceRepository->findBy(['refundPayment' => $refundPayment]);
        self::assertCount(1, $adyenReferences);

        /** @var AdyenReferenceInterface $adyenReference */
        $adyenReference = $adyenReferences[0];
        self::assertEquals($payment, $adyenReference->getPayment());
        self::assertEquals($refundPayment, $adyenReference->getRefundPayment());
        self::assertNotNull($adyenReference->getPspReference());
    }

    private function setupPaidOrderWithAdyenPayment(): void
    {
        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => 'Authorised',
            'pspReference' => 'TEST_PSP_REF_123',
            'merchantReference' => $this->testOrder->getNumber(),
        ]);

        $request = $this->createCheckoutRequest([
            'paymentMethod' => [
                'type' => 'scheme',
                'encryptedCardNumber' => 'test_encrypted_number',
                'encryptedExpiryMonth' => 'test_encrypted_month',
                'encryptedExpiryYear' => 'test_encrypted_year',
                'encryptedSecurityCode' => 'test_encrypted_cvv',
            ],
        ]);

        $response = ($this->paymentsAction)($request);
        self::assertEquals(200, $response->getStatusCode());

        $payment = $this->testOrder->getLastPayment();
        $this->simulateWebhook($payment, 'authorisation');

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();

        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());

        // Ensure the order payment state is set to paid
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_PAID);
        $entityManager->flush();
    }

    private function createTestOrder(): OrderInterface
    {
        $order = new Order();

        $uniqueId = (int) (microtime(true) * 1000) + rand(1, 999);
        $uniqueNumber = 'ORDER_' . $uniqueId;

        $reflection = new \ReflectionClass($order);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($order, $uniqueId);

        $order->setNumber($uniqueNumber);
        $order->setTokenValue('test_token_' . $uniqueId);
        $order->setLocaleCode('en_US');
        $order->setCurrencyCode('USD');
        $order->setState(OrderInterface::STATE_NEW);

        $customer = new Customer();
        $customer->setEmail('test' . $uniqueId . '@example.com');
        $customer->setFirstName('Test');
        $customer->setLastName('Customer');
        $order->setCustomer($customer);

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_NEW);
        $payment->setAmount(10000);
        $payment->setCurrencyCode('USD');
        $order->addPayment($payment);

        return $order;
    }

    private function createCheckoutRequest(array $paymentData): Request
    {
        $container = self::getContainer();
        $sessionFactory = $container->get('session.factory');
        $session = $sessionFactory->createSession();

        $request = new Request(
            [],
            $paymentData,
            ['_locale' => 'en_US'],
            [],
            [],
            ['HTTP_HOST' => 'localhost'],
        );
        $request->setSession($session);
        $request->setLocale('en_US');

        $session->set('sylius_order_id', $this->testOrder->getId());

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        return $request;
    }

    private function simulateWebhook(PaymentInterface $payment, string $eventCode): void
    {
        $notificationData = new NotificationItemData();
        $notificationData->eventCode = $eventCode;
        $notificationData->success = true;
        $notificationData->merchantReference = $payment->getOrder()?->getNumber() ?? 'TEST_ORDER';
        $notificationData->paymentMethod = 'scheme';

        // Handle refund-specific data
        if ($eventCode === 'refund') {
            $notificationData->pspReference = 'REFUND_PSP_REF_456';
            $notificationData->originalReference = $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF';

            // Set amount data for refund
            $amountData = new Amount();
            $amountData->value = $payment->getAmount();
            $amountData->currency = $payment->getCurrencyCode();
            $notificationData->amount = $amountData;
        } else {
            // For other event types (cancellation, authorisation, etc.)
            $notificationData->pspReference = $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF';
        }

        $command = $this->paymentCommandFactory->createForEvent(
            $eventCode,
            $payment,
            $notificationData,
        );

        $this->messageBus->dispatch($command);
    }
}
