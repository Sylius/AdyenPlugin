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
use Sylius\AdyenPlugin\Controller\Admin\GeneratePayLinkAction;
use Sylius\AdyenPlugin\Controller\Shop\PaymentDetailsAction;
use Sylius\AdyenPlugin\Controller\Shop\PaymentsAction;
use Sylius\AdyenPlugin\Controller\Shop\ProcessNotificationsAction;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
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

        $this->initializeServices($container);
    }

    protected function tearDown(): void
    {
//        $this->purgeDatabase();
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
        $webhookData = [
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

        return $webhookData;
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

    protected function getGeneratePayLinkAction(): GeneratePayLinkAction
    {
        return self::getContainer()->get('sylius_adyen.controller.admin.payment.generate_pay_link');
    }

    protected function setupTestCartContext(): void
    {
        $testCartContext = self::getContainer()->get('sylius_adyen.test.cart_context');
        $testCartContext->setOrder($this->testOrder);
    }
}
