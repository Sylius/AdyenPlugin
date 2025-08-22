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
use Sylius\AdyenPlugin\Controller\Admin\GeneratePayLinkAction;
use Sylius\AdyenPlugin\Controller\Shop\ProcessNotificationsAction;
use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\AdyenPlugin\Entity\PaymentLink;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\AdyenReferenceRepositoryInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig;
use Tests\Sylius\AdyenPlugin\Functional\Stub\AdyenClientStub;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

final class PaymentLinkProcessTest extends WebTestCase
{
    private OrderInterface $testOrder;

    private ProcessNotificationsAction $processNotificationsAction;

    private GeneratePayLinkAction $generatePayLinkAction;

    private AdyenClientStub $adyenClientStub;

    private PaymentLinkRepositoryInterface $paymentLinkRepository;

    private AdyenReferenceRepositoryInterface $adyenReferenceRepository;

    private static PaymentMethod $sharedPaymentMethod;

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
            'hmacKey' => '70E6897824A012655666C5235F50D44F2CDF284A91352B2E8F53B1D5A0189A43',
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

        $this->processNotificationsAction = $container->get('sylius_adyen.controller.shop.process_notifications');
        $this->generatePayLinkAction = $container->get('sylius_adyen.controller.admin.payment.generate_pay_link');
        $this->adyenClientStub = $container->get('sylius_adyen.test.adyen_client_stub');
        $this->paymentLinkRepository = $container->get('sylius_adyen.repository.payment_link');
        $this->adyenReferenceRepository = $container->get('sylius_adyen.repository.adyen_reference');
    }

    protected function tearDown(): void
    {
        $this->purgeDatabase();

        parent::tearDown();
    }

    public function testPaymentLinkGeneration(): void
    {
        $this->testOrder->setState(OrderInterface::STATE_NEW);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_NEW);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_AWAITING_PAYMENT, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_NEW, $payment->getState());

        $paymentLinks = $this->paymentLinkRepository->findBy(['payment' => $payment]);
        self::assertCount(0, $paymentLinks);

        $this->adyenClientStub->setPaymentLinkResponse([
            'id' => 'PL_TEST_GENERATED_123456',
            'url' => 'https://test.adyen.link/PL_TEST_GENERATED_123456',
            'expiresAt' => '2024-12-31T23:59:59Z',
            'reference' => $this->testOrder->getNumber(),
            'amount' => [
                'value' => $payment->getAmount(),
                'currency' => $payment->getCurrencyCode(),
            ],
            'merchantAccount' => 'test_merchant',
            'status' => 'active',
        ]);

        $request = $this->createRequest();
        $response = ($this->generatePayLinkAction)((string) $payment->getId(), $request);

        self::assertInstanceOf(RedirectResponse::class, $response);

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_AWAITING_PAYMENT, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());

        $createdPaymentLinks = $this->paymentLinkRepository->findBy(['payment' => $payment]);
        self::assertCount(1, $createdPaymentLinks, 'A new PaymentLink should be created');

        /** @var PaymentLinkInterface $paymentLink */
        $paymentLink = $createdPaymentLinks[0];
        self::assertEquals('PL_TEST_GENERATED_123456', $paymentLink->getPaymentLinkId());
        self::assertEquals($payment, $paymentLink->getPayment());

        $paymentDetails = $payment->getDetails();
        self::assertArrayHasKey('id', $paymentDetails);
        self::assertEquals('PL_TEST_GENERATED_123456', $paymentDetails['id']);
        self::assertArrayHasKey('url', $paymentDetails);
        self::assertEquals('https://test.adyen.link/PL_TEST_GENERATED_123456', $paymentDetails['url']);
        self::assertArrayHasKey('status', $paymentDetails);
        self::assertEquals('active', $paymentDetails['status']);
    }

    public function testSuccessfulAuthorizationThroughPaymentLink(): void
    {
        $this->testOrder->setState(OrderInterface::STATE_NEW);
        $this->testOrder->setPaymentState(OrderPaymentStates::STATE_AWAITING_PAYMENT);

        $payment = $this->testOrder->getLastPayment();
        $payment->setState(PaymentInterface::STATE_PROCESSING);

        $paymentLinkId = 'PL_TEST_LINK_ID_123456';
        $paymentLink = new PaymentLink($payment, $paymentLinkId);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->persist($paymentLink);
        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_AWAITING_PAYMENT, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_PROCESSING, $payment->getState());

        $existingPaymentLink = $this->paymentLinkRepository->findOneBy(['paymentLinkId' => $paymentLinkId]);
        self::assertNotNull($existingPaymentLink);
        self::assertInstanceOf(PaymentLinkInterface::class, $existingPaymentLink);
        self::assertEquals($paymentLinkId, $existingPaymentLink->getPaymentLinkId());
        self::assertEquals($payment, $existingPaymentLink->getPayment());

        $adyenReferences = $this->adyenReferenceRepository->findBy(['payment' => $payment]);
        self::assertCount(0, $adyenReferences);

        $webhookData = [
            'live' => 'false',
            'notificationItems' => [[
                'NotificationRequestItem' => [
                    'eventCode' => 'AUTHORISATION',
                    'success' => 'true',
                    'eventDate' => '2024-01-01T00:00:00+00:00',
                    'merchantAccountCode' => 'test_merchant',
                    'pspReference' => 'AUTH_PSP_REF_789',
                    'merchantReference' => $this->testOrder->getNumber(),
                    'amount' => [
                        'value' => $payment->getAmount(),
                        'currency' => $payment->getCurrencyCode(),
                    ],
                    'paymentMethod' => 'scheme',
                    'additionalData' => [
                        'paymentLinkId' => $paymentLinkId,
                        'hmacSignature' => 'PXw8ooqKq7yCsTt3ZKDlQi7wsD+u9IY7VTiW3QtDk7E=',
                    ],
                ],
            ]],
        ];

        $request = $this->createWebhookRequest($webhookData);
        $response = ($this->processNotificationsAction)('adyen_checkout', $request);

        self::assertEquals('[accepted]', $response->getContent());

        $entityManager->flush();

        self::assertEquals(OrderInterface::STATE_NEW, $this->testOrder->getState());
        self::assertEquals(OrderPaymentStates::STATE_PAID, $this->testOrder->getPaymentState());
        self::assertEquals(PaymentInterface::STATE_COMPLETED, $payment->getState());

        $deletedPaymentLink = $this->paymentLinkRepository->findOneBy(['paymentLinkId' => $paymentLinkId]);
        self::assertNull($deletedPaymentLink, 'Payment link should be removed after successful authorization');

        $adyenReferences = $this->adyenReferenceRepository->findBy(['payment' => $payment]);
        self::assertCount(1, $adyenReferences, 'A new AdyenReference should be created for the payment');

        /** @var AdyenReferenceInterface $adyenReference */
        $adyenReference = $adyenReferences[0];
        self::assertEquals('AUTH_PSP_REF_789', $adyenReference->getPspReference());
        self::assertEquals($payment, $adyenReference->getPayment());

        $paymentDetails = $payment->getDetails();
        self::assertArrayHasKey('pspReference', $paymentDetails);
        self::assertEquals('AUTH_PSP_REF_789', $paymentDetails['pspReference']);
        self::assertArrayHasKey('paymentLinkId', $paymentDetails['additionalData']);
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

    private function createRequest(): Request
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
        );
        $request->setSession($session);
        $request->setLocale('en_US');

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        return $request;
    }

    private function createWebhookRequest(array $data): Request
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

        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        return $request;
    }
}
