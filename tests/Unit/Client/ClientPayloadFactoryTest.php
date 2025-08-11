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

namespace Tests\Sylius\AdyenPlugin\Unit\Client;

use Doctrine\Common\Collections\ArrayCollection;
use Payum\Core\Bridge\Spl\ArrayObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Client\ClientPayloadFactory;
use Sylius\AdyenPlugin\Collector\CompositeEsdCollector;
use Sylius\AdyenPlugin\Collector\CompositeEsdCollectorInterface;
use Sylius\AdyenPlugin\Collector\ItemDetailLineCollector;
use Sylius\AdyenPlugin\Collector\Level2EsdCollector;
use Sylius\AdyenPlugin\Collector\Level3EsdCollector;
use Sylius\AdyenPlugin\Resolver\Version\VersionResolverInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class ClientPayloadFactoryTest extends TestCase
{
    private ClientPayloadFactory $factory;

    private VersionResolverInterface $versionResolver;

    private NormalizerInterface $normalizer;

    private RequestStack $requestStack;

    private CompositeEsdCollectorInterface $esdCollector;

    protected function setUp(): void
    {
        $this->versionResolver = $this->createMock(VersionResolverInterface::class);
        $this->normalizer = $this->createMock(NormalizerInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        // Create real ESD collector with Level 2 and Level 3 collectors
        $level2Collector = new Level2EsdCollector();
        $itemDetailLineCollector = new ItemDetailLineCollector();
        $this->esdCollector = new CompositeEsdCollector([
            'level2' => $level2Collector,
            'level3' => new Level3EsdCollector($level2Collector, $itemDetailLineCollector),
        ], ['USD'], ['US']);

        $this->factory = new ClientPayloadFactory(
            $this->versionResolver,
            $this->normalizer,
            $this->requestStack,
            $this->esdCollector,
            ['visa', 'mc'], // Default supported card brands
        );

        // Default version resolver behavior
        $this->versionResolver->expects($this->any())
            ->method('appendVersionConstraints')
            ->willReturnArgument(0);
    }

    public function testItAddsEsdForCardPaymentsInSubmitPayment(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => true,
            'esdType' => 'level3',
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->any())
            ->method('getTotal')
            ->willReturn(10000);
        $order->expects($this->any())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->any())
            ->method('getNumber')
            ->willReturn('ORD-001');
        $order->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($billingAddress);

        $receivedPayload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
            ],
        ];

        // Add more order details for the real collector
        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->any())
            ->method('getId')
            ->willReturn(123);

        $order->expects($this->any())
            ->method('getCustomer')
            ->willReturn($customer);
        $order->expects($this->any())
            ->method('getTaxTotal')
            ->willReturn(100);
        $order->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn(null);
        $order->expects($this->any())
            ->method('getItems')
            ->willReturn(new ArrayCollection());
        $order->expects($this->any())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime('2023-01-01'));
        $order->expects($this->any())
            ->method('getShippingTotal')
            ->willReturn(500);

        $result = $this->factory->createForSubmitPayment(
            $options,
            'https://example.com/return',
            $receivedPayload,
            $order,
        );

        $this->assertArrayHasKey('additionalData', $result);
        $this->assertEquals('123', $result['additionalData']['enhancedSchemeData.customerReference']);
        $this->assertEquals(100, $result['additionalData']['enhancedSchemeData.totalTaxAmount']);
    }

    public function testItDoesNotAddEsdForNonCardPayments(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => true,
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->any())
            ->method('getTotal')
            ->willReturn(10000);
        $order->expects($this->any())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->any())
            ->method('getNumber')
            ->willReturn('ORD-002');
        $order->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($billingAddress);

        $receivedPayload = [
            'paymentMethod' => [
                'type' => 'ideal',
            ],
        ];

        // No ESD data should be added for non-card payments

        $result = $this->factory->createForSubmitPayment(
            $options,
            'https://example.com/return',
            $receivedPayload,
            $order,
        );

        $this->assertArrayNotHasKey('enhancedSchemeData.customerReference', $result['additionalData'] ?? []);
    }

    public function testItDoesNotAddEsdWhenDisabled(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => false,
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->any())
            ->method('getTotal')
            ->willReturn(10000);
        $order->expects($this->any())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->any())
            ->method('getNumber')
            ->willReturn('ORD-003');
        $order->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($billingAddress);

        $receivedPayload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'visa',
            ],
        ];

        // No ESD data should be added for non-card payments

        $result = $this->factory->createForSubmitPayment(
            $options,
            'https://example.com/return',
            $receivedPayload,
            $order,
        );

        $this->assertArrayNotHasKey('enhancedSchemeData.customerReference', $result['additionalData'] ?? []);
    }

    public function testItAddsEsdForCaptureRequests(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => true,
        ]);

        $order = $this->createMock(OrderInterface::class);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())
            ->method('getAmount')
            ->willReturn(10000);
        $payment->expects($this->once())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $payment->expects($this->any())
            ->method('getDetails')
            ->willReturn([
                'pspReference' => 'PSP123',
                'paymentMethod' => ['brand' => 'mc'],
            ]);
        $payment->expects($this->once())
            ->method('getOrder')
            ->willReturn($order);

        // Add order details for the real collector
        $customer = $this->createMock(CustomerInterface::class);
        $customer->expects($this->any())
            ->method('getId')
            ->willReturn(456);

        $order->expects($this->any())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->any())
            ->method('getCustomer')
            ->willReturn($customer);
        $order->expects($this->any())
            ->method('getTaxTotal')
            ->willReturn(200);
        $order->expects($this->any())
            ->method('getShippingAddress')
            ->willReturn(null);
        $order->expects($this->any())
            ->method('getItems')
            ->willReturn(new ArrayCollection());
        $order->expects($this->any())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime('2023-01-01'));
        $order->expects($this->any())
            ->method('getShippingTotal')
            ->willReturn(500);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())
            ->method('getCountryCode')
            ->willReturn('US');
        $order->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($billingAddress);

        $result = $this->factory->createForCapture($options, $payment);

        $this->assertArrayHasKey('additionalData', $result);
        $this->assertEquals('456', $result['additionalData']['enhancedSchemeData.customerReference']);
        $this->assertEquals(200, $result['additionalData']['enhancedSchemeData.totalTaxAmount']);
    }

    public function testItDoesNotAddEsdForNonVisaMastercardBrands(): void
    {
        $options = new ArrayObject([
            'merchantAccount' => 'TestMerchant',
            'esdEnabled' => true,
        ]);

        $billingAddress = $this->createMock(AddressInterface::class);
        $billingAddress->expects($this->any())
            ->method('getCountryCode')
            ->willReturn('US');

        $order = $this->createMock(OrderInterface::class);
        $order->expects($this->any())
            ->method('getTotal')
            ->willReturn(10000);
        $order->expects($this->any())
            ->method('getCurrencyCode')
            ->willReturn('USD');
        $order->expects($this->any())
            ->method('getNumber')
            ->willReturn('ORD-004');
        $order->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($billingAddress);

        $receivedPayload = [
            'paymentMethod' => [
                'type' => 'scheme',
                'brand' => 'amex', // American Express - not Visa/Mastercard
            ],
        ];

        // No ESD data should be added for non-card payments

        $result = $this->factory->createForSubmitPayment(
            $options,
            'https://example.com/return',
            $receivedPayload,
            $order,
        );

        $this->assertArrayNotHasKey('enhancedSchemeData.customerReference', $result['additionalData'] ?? []);
    }
}
