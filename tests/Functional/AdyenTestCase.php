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
use Sylius\AdyenPlugin\Resolver\Payment\EventCodeResolverInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemUnit;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Payment\Model\PaymentMethodTranslation;
use Sylius\Component\Product\Model\ProductVariantTranslation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\AdyenPlugin\Entity\ProductVariant;
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

        $paymentMethodTranslation = new PaymentMethodTranslation();
        $paymentMethodTranslation->setLocale('en_US');
        $paymentMethodTranslation->setName(self::PAYMENT_METHOD_NAME);

        self::$sharedPaymentMethod = new PaymentMethod();
        self::$sharedPaymentMethod->setCode(self::PAYMENT_METHOD_CODE);
        self::$sharedPaymentMethod->setCurrentLocale('en_US');
        self::$sharedPaymentMethod->setFallbackLocale('en_US');
        self::$sharedPaymentMethod->addTranslation($paymentMethodTranslation);

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

    protected function initializeServices(ContainerInterface $container): void
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

    protected function createTestOrder(bool $setup = true): OrderInterface
    {
        $order = new Order();

        $uniqueId = (int) (microtime(true) * 1000) + random_int(1, 999);
        $uniqueNumber = 'ORDER_' . $uniqueId;

        $reflection = new \ReflectionClass($order);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($order, $uniqueId);

        if ($setup) {
            $locale = new Locale();
            $locale->setCode('en_US');
            $this->getEntityManager()->persist($locale);

            $currency = new Currency();
            $currency->setCode('USD');
            $this->getEntityManager()->persist($currency);

            $channel = new Channel();
            $channel->setCode('WEB_US');
            $channel->setName('Web US');
            $channel->setDefaultLocale($locale);
            $channel->addLocale($locale);
            $channel->setBaseCurrency($currency);
            $channel->addCurrency($currency);
            $channel->setTaxCalculationStrategy('order_items_based');
            $this->getEntityManager()->persist($channel);

            $order->setChannel($channel);

            $billingAddress = new Address();
            $billingAddress->setFirstName('John');
            $billingAddress->setLastName('Doe');
            $billingAddress->setStreet('123 Main St');
            $billingAddress->setCity('Anytown');
            $billingAddress->setPostcode('12345');
            $billingAddress->setCountryCode('US');
            $billingAddress->setProvinceCode('CA');
            $billingAddress->setPhoneNumber('555-1234');

            $order->setBillingAddress($billingAddress);

            $productTranslation = new ProductTranslation();
            $productTranslation->setLocale('en_US');
            $productTranslation->setName('Test Product');
            $productTranslation->setSlug('test-product');

            $product = new Product();
            $product->setCode('TEST_PRODUCT_' . $uniqueId);
            $product->setCurrentLocale('en_US');
            $product->addTranslation($productTranslation);
            $this->getEntityManager()->persist($product);

            $variantTranslation = new ProductVariantTranslation();
            $variantTranslation->setLocale('en_US');

            $variant = new ProductVariant();
            $variant->setCode('TEST_VARIANT_' . $uniqueId);
            $variant->setProduct($product);
            $variant->setCurrentLocale('en_US');
            $variant->addTranslation($variantTranslation);
            $this->getEntityManager()->persist($variant);

            $channelPricing = new ChannelPricing();
            $channelPricing->setChannelCode('WEB_US');
            $channelPricing->setPrice(10000);
            $variant->addChannelPricing($channelPricing);

            $orderItem = new OrderItem();
            $orderItem->setVariant($variant);
            $orderItem->setUnitPrice(10000);

            $orderItemUnit = new OrderItemUnit($orderItem);
            $orderItem->addUnit($orderItemUnit);

            $unitReflection = new \ReflectionClass($orderItemUnit);
            $unitIdProperty = $unitReflection->getProperty('id');
            $unitIdProperty->setAccessible(true);
            $unitIdProperty->setValue($orderItemUnit, $uniqueId);

            $order->addItem($orderItem);
        }

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
            [],
            ['_locale' => 'en_US'],
            [],
            [],
            ['HTTP_HOST' => 'localhost'],
            json_encode($data),
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

    protected function simulateWebhook(
        PaymentInterface $payment,
        string $eventCode,
        bool $success = true,
        array $additionalData = [],
        ?string $pspReference = null,
        ?string $merchantReference = null,
        ?string $paymentLinkId = null,
        ?string $originalReference = null,
    ): Response {
        $webhookData = $this->createWebhookData(
            $eventCode,
            $pspReference ?? $payment->getDetails()['pspReference'] ?? 'TEST_PSP_REF',
            $merchantReference ?? $payment->getOrder()?->getNumber() ?? 'TEST_ORDER',
            $additionalData,
            $success,
            $paymentLinkId,
            $originalReference,
        );

        $request = $this->createWebhookRequest($webhookData);

        return ($this->getProcessNotificationsAction())(self::PAYMENT_METHOD_CODE, $request);
    }

    protected function createWebhookData(
        string $eventCode,
        string $pspReference,
        string $merchantReference,
        array $additionalData = [],
        bool $success = true,
        ?string $paymentLinkId = null,
        ?string $originalReference = null,
    ): array {
        $notificationItem = [
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
        ];

        // Add originalReference if provided as parameter
        if ($originalReference !== null) {
            $notificationItem['originalReference'] = $originalReference;
        }

        return [
            'live' => 'false',
            'notificationItems' => [[
                'NotificationRequestItem' => $notificationItem,
            ]],
        ];
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
        $this->simulateWebhook($payment, EventCodeResolverInterface::EVENT_AUTHORIZATION);

        $this->testOrder->setCheckoutState(OrderCheckoutStates::STATE_COMPLETED);
        $this->testOrder->setState(OrderInterface::STATE_NEW);

        $this->getEntityManager()->flush();
    }
}
