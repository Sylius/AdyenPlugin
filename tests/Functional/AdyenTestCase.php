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
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Controller\Admin\CaptureOrderPaymentAction;
use Sylius\AdyenPlugin\Controller\Admin\GeneratePayLinkAction;
use Sylius\AdyenPlugin\Controller\Shop\PaymentDetailsAction;
use Sylius\AdyenPlugin\Controller\Shop\PaymentsAction;
use Sylius\AdyenPlugin\Controller\Shop\ProcessNotificationsAction;
use Sylius\AdyenPlugin\PaymentCaptureMode;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\Amount;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\OrderCheckoutStates;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Tests\Sylius\AdyenPlugin\Functional\Stub\AdyenClientStub;

abstract class AdyenTestCase extends WebTestCase
{
    protected OrderInterface $testOrder;

    protected AdyenClientStub $adyenClientStub;

    protected PaymentLinkRepositoryInterface $paymentLinkRepository;

    protected AdyenReferenceRepositoryInterface $adyenReferenceRepository;

    protected StateMachineInterface $stateMachine;

    protected static PaymentMethod $sharedPaymentMethod;

    protected const TEST_HMAC_KEY = '70E6897824A012655666C5235F50D44F2CDF284A91352B2E8F53B1D5A0189A43';

    protected const TEST_HMAC_SIGNATURE = 'PXw8ooqKq7yCsTt3ZKDlQi7wsD+u9IY7VTiW3QtDk7E=';

    protected const PAYMENT_METHOD_CODE = 'adyen_checkout';

    protected const PAYMENT_METHOD_NAME = 'Adyen Checkout';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$sharedPaymentMethod = new PaymentMethod();
        self::$sharedPaymentMethod->setCode(self::PAYMENT_METHOD_CODE);
        self::$sharedPaymentMethod->setCurrentLocale('en_US');
        self::$sharedPaymentMethod->setFallbackLocale('en_US');
        self::$sharedPaymentMethod->setName(self::PAYMENT_METHOD_NAME);

        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName(AdyenClientProviderInterface::FACTORY_NAME);
        $gatewayConfig->setGatewayName(self::PAYMENT_METHOD_CODE);
        $gatewayConfig->setConfig([
            'environment' => 'test',
            'merchant_account' => 'test_merchant',
            'api_key' => 'test_api_key',
            'client_key' => 'test_client_key',
            'hmacKey' => self::TEST_HMAC_KEY,
        ]);

        self::$sharedPaymentMethod->setGatewayConfig($gatewayConfig);
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
        $this->paymentLinkRepository = $container->get('sylius_adyen.repository.payment_link');
        $this->adyenReferenceRepository = $container->get('sylius_adyen.repository.adyen_reference');

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = $container->get('sylius_abstraction.state_machine');
        $this->stateMachine = $stateMachine;

        $this->initializeServices($container);
    }

    protected function tearDown(): void
    {
        $this->purgeDatabase();
        parent::tearDown();
    }

    protected function initializeServices($container): void
    {
        // Override in child classes to initialize specific services
    }

    protected function purgeDatabase(): void
    {
        $container = self::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $purger = new ORMPurger($entityManager);
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
        $purger->purge();

        $entityManager->clear();
    }

    protected function createTestOrder(): OrderInterface
    {
        $order = new Order();

        $uniqueId = (int) (microtime(true) * 1000) + random_int(1, 999);
        $uniqueNumber = 'ORDER_' . $uniqueId;

        $reflection = new \ReflectionClass($order);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($order, $uniqueId);

        $order->setNumber($uniqueNumber);
        $order->setTokenValue('test_token_' . $uniqueId);
        $order->setLocaleCode('en_US');
        $order->setCurrencyCode('USD');
        $order->setCheckoutState(OrderCheckoutStates::STATE_PAYMENT_SELECTED);

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

    protected function createWebhookRequest(array $data): Request
    {
        return $this->createRequest($data);
    }

    protected function createRequest(array $data = []): Request
    {
        $container = self::getContainer();
        $sessionFactory = $container->get('session.factory');
        $session = $sessionFactory->createSession();

        $request = new Request(
            [],
            $data,
            ['_locale' => 'en_US'],
            [],
            [],
            ['HTTP_HOST' => 'localhost'],
        );
        $request->setSession($session);
        $request->setLocale('en_US');

        if ($data !== []) {
            $session->set('sylius_order_id', $this->testOrder->getId());
        }

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        return $request;
    }

    protected function createWebhookData(
        string $eventCode,
        string $pspReference,
        string $merchantReference,
        array $additionalData = [],
        bool $success = true,
        ?string $paymentLinkId = null,
    ): array {
        return [
            'live' => 'false',
            'notificationItems' => [[
                'NotificationRequestItem' => [
                    'eventCode' => strtoupper($eventCode),
                    'success' => $success ? 'true' : 'false',
                    'eventDate' => '2024-01-01T00:00:00+00:00',
                    'merchantAccountCode' => 'test_merchant',
                    'pspReference' => $pspReference,
                    'merchantReference' => $merchantReference,
                    'amount' => [
                        'value' => 10000,
                        'currency' => 'USD',
                    ],
                    'paymentMethod' => 'scheme',
                    'additionalData' => array_merge(
                        ['hmacSignature' => self::TEST_HMAC_SIGNATURE],
                        $paymentLinkId ? ['paymentLinkId' => $paymentLinkId] : [],
                        $additionalData,
                    ),
                ],
            ]],
        ];
    }

    protected function setCaptureMode(string $captureMode): void
    {
        $config = self::$sharedPaymentMethod->getGatewayConfig()->getConfig();
        $config['captureMode'] = $captureMode;
        self::$sharedPaymentMethod->getGatewayConfig()->setConfig($config);
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function getPaymentsAction(): PaymentsAction
    {
        return self::getContainer()->get('sylius_adyen.controller.shop.payments');
    }

    protected function getPaymentDetailsAction(): PaymentDetailsAction
    {
        return self::getContainer()->get('sylius_adyen.controller.shop.payment_details');
    }

    protected function getProcessNotificationsAction(): ProcessNotificationsAction
    {
        return self::getContainer()->get('sylius_adyen.controller.shop.process_notifications');
    }

    protected function getCaptureOrderPaymentAction(): CaptureOrderPaymentAction
    {
        return self::getContainer()->get('sylius_adyen.controller.admin.order_payment.capture');
    }

    protected function getGeneratePayLinkAction(): GeneratePayLinkAction
    {
        return self::getContainer()->get('sylius_adyen.controller.admin.payment.generate_pay_link');
    }

    protected function setupTestCartContext(): void
    {
        $testCartContext = self::getContainer()->get('sylius_adyen.test.cart_context');
        $testCartContext->setOrder($this->testOrder);
    }

    /**
     * Simulates a webhook using the HTTP endpoint approach (via ProcessNotificationsAction).
     * This is the most common approach used in checkout and capture tests.
     */
    protected function simulateWebhookViaHttp(
        PaymentInterface $payment,
        string $eventCode,
        bool $success = true,
        array $additionalData = [],
        ?string $pspReference = null,
        ?string $merchantReference = null,
        ?string $paymentLinkId = null,
    ): void {
        $webhookData = $this->createWebhookData(
            $eventCode,
            $pspReference ?? $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF',
            $merchantReference ?? $payment->getOrder()?->getNumber() ?? 'TEST_ORDER',
            $additionalData,
            $success,
            $paymentLinkId,
        );

        $request = $this->createWebhookRequest($webhookData);
        ($this->getProcessNotificationsAction())(self::PAYMENT_METHOD_CODE, $request);
    }

    /**
     * Simulates a webhook via HTTP and returns the response.
     * Useful when you need to check the response content.
     */
    protected function simulateWebhookViaHttpWithResponse(
        PaymentInterface $payment,
        string $eventCode,
        bool $success = true,
        array $additionalData = [],
        ?string $pspReference = null,
        ?string $merchantReference = null,
        ?string $paymentLinkId = null,
    ) {
        $webhookData = $this->createWebhookData(
            $eventCode,
            $pspReference ?? $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF',
            $merchantReference ?? $payment->getOrder()?->getNumber() ?? 'TEST_ORDER',
            $additionalData,
            $success,
            $paymentLinkId,
        );

        $request = $this->createWebhookRequest($webhookData);

        return ($this->getProcessNotificationsAction())(self::PAYMENT_METHOD_CODE, $request);
    }

    /**
     * Simulates a webhook using the direct command dispatch approach.
     * This approach is used in payment reversal tests for more complex scenarios.
     */
    protected function simulateWebhookViaCommand(
        PaymentInterface $payment,
        string $eventCode,
        bool $success = true,
        ?string $pspReference = null,
        ?string $originalReference = null,
    ): void {
        $paymentCommandFactory = self::getContainer()->get('sylius_adyen.bus.payment_command_factory');
        $messageBus = self::getContainer()->get('sylius.command_bus');

        $notificationData = new NotificationItemData();
        $notificationData->eventCode = $eventCode;
        $notificationData->success = $success;
        $notificationData->merchantReference = $payment->getOrder()?->getNumber() ?? 'TEST_ORDER';
        $notificationData->paymentMethod = 'scheme';

        if ($eventCode === 'refund') {
            $notificationData->pspReference = $pspReference ?? 'REFUND_PSP_REF_456';
            $notificationData->originalReference = $originalReference ?? $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF';

            $amountData = new Amount();
            $amountData->value = $payment->getAmount();
            $amountData->currency = $payment->getCurrencyCode();
            $notificationData->amount = $amountData;
        } else {
            $notificationData->pspReference = $pspReference ?? $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF';
        }

        $command = $paymentCommandFactory->createForEvent(
            $eventCode,
            $payment,
            $notificationData,
        );

        $messageBus->dispatch($command);
    }

    /**
     * Convenience method that automatically chooses the appropriate webhook simulation method.
     * Uses HTTP approach by default, but uses command approach for refund events.
     */
    protected function simulateWebhook(
        PaymentInterface $payment,
        string $eventCode,
        bool $success = true,
        array $additionalData = [],
        ?string $pspReference = null,
        ?string $merchantReference = null,
        ?string $paymentLinkId = null,
    ): void {
        // For refund events, use the command approach for better handling of complex refund logic
        if ($eventCode === 'refund') {
            $this->simulateWebhookViaCommand($payment, $eventCode, $success, $pspReference);
        } else {
            $this->simulateWebhookViaHttp($payment, $eventCode, $success, $additionalData, $pspReference, $merchantReference, $paymentLinkId);
        }
    }

    protected function setupOrderWithAdyenPayment(
        string $captureMode = PaymentCaptureMode::AUTOMATIC,
        ?array $paymentMethodData = null,
        string $expectedResultCode = 'Authorised',
        string $expectedPspReference = 'TEST_PSP_REF_123',
        ?string $merchantReference = null,
    ): void {
        $paymentMethodData ??= [
            'type' => 'scheme',
            'encryptedCardNumber' => 'test_encrypted_number',
            'encryptedExpiryMonth' => 'test_encrypted_month',
            'encryptedExpiryYear' => 'test_encrypted_year',
            'encryptedSecurityCode' => 'test_encrypted_cvv',
        ];

        $merchantReference ??= $this->testOrder->getNumber();

        // Update the payment method's capture mode for this test
        $gatewayConfig = self::$sharedPaymentMethod->getGatewayConfig();
        $config = $gatewayConfig->getConfig();
        $config['captureMode'] = $captureMode;
        $gatewayConfig->setConfig($config);

        $this->getEntityManager()->flush();

        $this->adyenClientStub->setSubmitPaymentResponse([
            'resultCode' => $expectedResultCode,
            'pspReference' => $expectedPspReference,
            'merchantReference' => $merchantReference,
        ]);

        $request = $this->createRequest([
            'paymentMethod' => $paymentMethodData,
        ]);

        $response = ($this->getPaymentsAction())($request);
        self::assertEquals(200, $response->getStatusCode());

        $responseData = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('resultCode', $responseData);
        self::assertEquals($expectedResultCode, $responseData['resultCode']);
        self::assertArrayHasKey('pspReference', $responseData);
        self::assertEquals($expectedPspReference, $responseData['pspReference']);
        self::assertArrayHasKey('redirect', $responseData);

        $payment = $this->testOrder->getLastPayment();
        $this->simulateWebhook($payment, 'authorisation');

        $this->testOrder->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_NEW);

        $this->getEntityManager()->flush();
    }
}
