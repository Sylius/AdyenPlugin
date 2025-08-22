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

namespace Tests\Sylius\AdyenPlugin\Unit\Generator;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\AdyenPlugin\Exception\PaymentLinkGenerationException;
use Sylius\AdyenPlugin\Factory\PaymentLinkFactoryInterface;
use Sylius\AdyenPlugin\Generator\PaymentLinkGenerator;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class PaymentLinkGeneratorTest extends TestCase
{
    private AdyenClientProviderInterface|MockObject $adyenClientProvider;

    private AdyenPaymentMethodCheckerInterface|MockObject $adyenPaymentMethodChecker;

    private MockObject|PaymentLinkRepositoryInterface $paymentLinkRepository;

    private MockObject|PaymentLinkFactoryInterface $paymentLinkFactory;

    private MockObject|StateMachineInterface $stateMachine;

    private EntityManagerInterface|MockObject $entityManager;

    private LoggerInterface|MockObject $logger;

    private PaymentLinkGenerator $generator;

    protected function setUp(): void
    {
        $this->adyenClientProvider = $this->createMock(AdyenClientProviderInterface::class);
        $this->adyenPaymentMethodChecker = $this->createMock(AdyenPaymentMethodCheckerInterface::class);
        $this->paymentLinkRepository = $this->createMock(PaymentLinkRepositoryInterface::class);
        $this->paymentLinkFactory = $this->createMock(PaymentLinkFactoryInterface::class);
        $this->stateMachine = $this->createMock(StateMachineInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->generator = new PaymentLinkGenerator(
            $this->adyenClientProvider,
            $this->adyenPaymentMethodChecker,
            $this->paymentLinkRepository,
            $this->paymentLinkFactory,
            $this->stateMachine,
            $this->entityManager,
            $this->logger,
            [PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING],
        );
    }

    public static function provideValidPaymentStates(): \Generator
    {
        yield 'new state with transition' => [PaymentInterface::STATE_NEW, true, 3];
        yield 'processing state with transition' => [PaymentInterface::STATE_PROCESSING, true, 3];
        yield 'new state without transition' => [PaymentInterface::STATE_NEW, false, 2];
        yield 'processing state without transition' => [PaymentInterface::STATE_PROCESSING, false, 2];
    }

    #[DataProvider('provideValidPaymentStates')]
    public function testGenerateSuccessfullyCreatesPaymentLink(string $paymentState, bool $canTransition, int $expectedFlushCount): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn($paymentState);

        $payment->expects($this->any())
            ->method('getId')
            ->willReturn(123);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $adyenClient = $this->createMock(AdyenClientInterface::class);
        $paymentLink = $this->createMock(PaymentLinkInterface::class);

        $apiResponse = [
            'status' => 'active',
            'url' => 'https://test.adyen.link/PL123456789',
            'id' => 'PL123456789',
            'reference' => 'payment-123',
        ];

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPaymentMethod')
            ->with($paymentMethod)
            ->willReturn(true);

        $this->adyenClientProvider
            ->expects($this->once())
            ->method('getForPaymentMethod')
            ->with($paymentMethod)
            ->willReturn($adyenClient);

        $this->paymentLinkRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['payment' => $payment])
            ->willReturn([]);

        $adyenClient
            ->expects($this->once())
            ->method('generatePaymentLink')
            ->with($payment)
            ->willReturn($apiResponse);

        $this->paymentLinkFactory
            ->expects($this->once())
            ->method('create')
            ->with($payment, 'PL123456789', 'https://test.adyen.link/PL123456789')
            ->willReturn($paymentLink);

        $this->entityManager
            ->expects($this->exactly($expectedFlushCount))
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($paymentLink);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS)
            ->willReturn($canTransition);

        if ($canTransition) {
            $this->stateMachine
                ->expects($this->once())
                ->method('apply')
                ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS);
        } else {
            $this->stateMachine
                ->expects($this->never())
                ->method('apply');
        }

        $payment->expects($this->once())
            ->method('setDetails')
            ->with($apiResponse);

        $result = $this->generator->generate($payment);

        self::assertSame($paymentLink, $result);
    }

    public function testGenerateExpiresAndRemovesOldLinksWhenPresent(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);

        $payment->expects($this->any())
            ->method('getId')
            ->willReturn(123);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $adyenClient = $this->createMock(AdyenClientInterface::class);
        $paymentLink = $this->createMock(PaymentLinkInterface::class);
        $oldPaymentLink1 = $this->createMock(PaymentLinkInterface::class);
        $oldPaymentLink2 = $this->createMock(PaymentLinkInterface::class);

        $oldPaymentLinks = [$oldPaymentLink1, $oldPaymentLink2];

        $apiResponse = [
            'status' => 'active',
            'url' => 'https://test.adyen.link/PL123456789',
            'id' => 'PL123456789',
        ];

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPaymentMethod')
            ->with($paymentMethod)
            ->willReturn(true);

        $this->adyenClientProvider
            ->expects($this->once())
            ->method('getForPaymentMethod')
            ->with($paymentMethod)
            ->willReturn($adyenClient);

        $this->paymentLinkRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['payment' => $payment])
            ->willReturn($oldPaymentLinks);

        $oldPaymentLink1
            ->expects($this->once())
            ->method('getPaymentLinkId')
            ->willReturn('OLD_LINK_1');

        $oldPaymentLink2
            ->expects($this->once())
            ->method('getPaymentLinkId')
            ->willReturn('OLD_LINK_2');

        $adyenClient
            ->expects($this->exactly(2))
            ->method('expirePaymentLink')
            ->with(self::callback(function ($linkId) {
                return in_array($linkId, ['OLD_LINK_1', 'OLD_LINK_2'], true);
            }));

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove')
            ->with(self::callback(function ($paymentLink) use ($oldPaymentLink1, $oldPaymentLink2) {
                return $paymentLink === $oldPaymentLink1 || $paymentLink === $oldPaymentLink2;
            }));

        $adyenClient
            ->expects($this->once())
            ->method('generatePaymentLink')
            ->with($payment)
            ->willReturn($apiResponse);

        $this->paymentLinkFactory
            ->expects($this->once())
            ->method('create')
            ->with($payment, 'PL123456789', 'https://test.adyen.link/PL123456789')
            ->willReturn($paymentLink);

        $this->entityManager
            ->expects($this->exactly(3))
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($paymentLink);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS)
            ->willReturn(true);

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS);

        $payment->expects($this->once())
            ->method('setDetails')
            ->with($apiResponse);

        $result = $this->generator->generate($payment);

        self::assertSame($paymentLink, $result);
    }

    public function testGenerateHandlesExpirePaymentLinkExceptionGracefully(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);

        $payment->expects($this->any())
            ->method('getId')
            ->willReturn(123);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $adyenClient = $this->createMock(AdyenClientInterface::class);
        $paymentLink = $this->createMock(PaymentLinkInterface::class);
        $oldPaymentLink = $this->createMock(PaymentLinkInterface::class);

        $apiResponse = [
            'status' => 'active',
            'url' => 'https://test.adyen.link/PL123456789',
            'id' => 'PL123456789',
        ];

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPaymentMethod')
            ->with($paymentMethod)
            ->willReturn(true);

        $this->adyenClientProvider
            ->expects($this->once())
            ->method('getForPaymentMethod')
            ->with($paymentMethod)
            ->willReturn($adyenClient);

        $this->paymentLinkRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['payment' => $payment])
            ->willReturn([$oldPaymentLink]);

        $oldPaymentLink
            ->expects($this->once())
            ->method('getPaymentLinkId')
            ->willReturn('OLD_LINK_ID');

        $adyenClient
            ->expects($this->once())
            ->method('expirePaymentLink')
            ->with('OLD_LINK_ID')
            ->willThrowException(new \Exception('Adyen API error'));

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($oldPaymentLink);

        $adyenClient
            ->expects($this->once())
            ->method('generatePaymentLink')
            ->with($payment)
            ->willReturn($apiResponse);

        $this->paymentLinkFactory
            ->expects($this->once())
            ->method('create')
            ->with($payment, 'PL123456789', 'https://test.adyen.link/PL123456789')
            ->willReturn($paymentLink);

        $this->entityManager
            ->expects($this->exactly(3))
            ->method('flush');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($paymentLink);

        $this->stateMachine
            ->expects($this->once())
            ->method('can')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS)
            ->willReturn(true);

        $this->stateMachine
            ->expects($this->once())
            ->method('apply')
            ->with($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS);

        $payment->expects($this->once())
            ->method('setDetails')
            ->with($apiResponse);

        $result = $this->generator->generate($payment);

        self::assertSame($paymentLink, $result);
    }

    public function testGenerateThrowsExceptionWhenGeneratePaymentLinkFails(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);

        $payment->expects($this->any())
            ->method('getId')
            ->willReturn(123);

        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $adyenClient = $this->createMock(AdyenClientInterface::class);
        $originalException = new \Exception('API connection failed');

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPaymentMethod')
            ->with($paymentMethod)
            ->willReturn(true);

        $this->adyenClientProvider
            ->expects($this->once())
            ->method('getForPaymentMethod')
            ->with($paymentMethod)
            ->willReturn($adyenClient);

        $this->paymentLinkRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['payment' => $payment])
            ->willReturn([]);

        $adyenClient
            ->expects($this->once())
            ->method('generatePaymentLink')
            ->with($payment)
            ->willThrowException($originalException);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('An error occurred during payment link generation.', [
                'paymentId' => 123,
                'exception' => $originalException,
            ]);

        $this->expectException(PaymentLinkGenerationException::class);
        $this->expectExceptionMessage('Payment link generation failed for payment with id 123. Error: API connection failed');

        $this->generator->generate($payment);
    }

    public function testGenerateThrowsExceptionWhenPaymentMethodIsNull(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);
        $payment->expects($this->any())
            ->method('getId')
            ->willReturn(123);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn(null);

        $this->expectException(PaymentLinkGenerationException::class);
        $this->expectExceptionMessage('Payment link generation failed for payment with id 123. Payment method is not an Adyen one.');

        $this->generator->generate($payment);
    }

    public function testGenerateThrowsExceptionWhenPaymentMethodIsNotAdyen(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);
        $payment->expects($this->any())
            ->method('getId')
            ->willReturn(123);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPaymentMethod')
            ->with($paymentMethod)
            ->willReturn(false);

        $this->expectException(PaymentLinkGenerationException::class);
        $this->expectExceptionMessage('Payment link generation failed for payment with id 123. Payment method is not an Adyen one.');

        $this->generator->generate($payment);
    }

    #[DataProvider('provideInvalidApiResponses')]
    public function testGenerateThrowsExceptionWhenAdyenApiResponseMissingRequiredFields(array $apiResponse): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);

        $payment->expects($this->once())
            ->method('getState')
            ->willReturn(PaymentInterface::STATE_NEW);
        $payment->expects($this->any())
            ->method('getId')
            ->willReturn(123);
        $payment->expects($this->once())
            ->method('getMethod')
            ->willReturn($paymentMethod);

        $adyenClient = $this->createMock(AdyenClientInterface::class);

        $this->adyenPaymentMethodChecker
            ->expects($this->once())
            ->method('isAdyenPaymentMethod')
            ->with($paymentMethod)
            ->willReturn(true);

        $this->adyenClientProvider
            ->expects($this->once())
            ->method('getForPaymentMethod')
            ->with($paymentMethod)
            ->willReturn($adyenClient);

        $this->paymentLinkRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['payment' => $payment])
            ->willReturn([]);

        $adyenClient
            ->expects($this->once())
            ->method('generatePaymentLink')
            ->with($payment)
            ->willReturn($apiResponse);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to generate payment link.', [
                'paymentId' => 123,
                'response' => $apiResponse,
            ]);

        $this->expectException(PaymentLinkGenerationException::class);
        $this->expectExceptionMessage('Payment link generation failed for payment with id 123. Adyen API did not return a valid payment link.');

        $this->generator->generate($payment);
    }

    public static function provideInvalidApiResponses(): \Generator
    {
        yield 'invalid status' => [
            [
                'status' => 'error',
                'id' => 'PL123456789',
                'url' => 'https://test.adyen.link/PL123456789',
            ],
        ];
        yield 'missing url' => [
            [
                'status' => 'active',
                'id' => 'PL123456789',
            ],
        ];
        yield 'missing id' => [
            [
                'status' => 'active',
                'url' => 'https://test.adyen.link/PL123456789',
            ],
        ];
        yield 'missing both url and id' => [
            [
                'status' => 'active',
            ],
        ];
    }

    #[DataProvider('provideInvalidPaymentStates')]
    public function testGenerateThrowsExceptionForInvalidPaymentStates(string $paymentState): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->expects($this->any())
            ->method('getState')
            ->willReturn($paymentState);
        $payment->expects($this->any())
            ->method('getId')
            ->willReturn(123);

        $this->expectException(PaymentLinkGenerationException::class);
        $this->expectExceptionMessage('Payment link generation failed for payment with id 123. Cannot generate a payment link for payment with state:');

        $this->generator->generate($payment);
    }

    public static function provideInvalidPaymentStates(): \Generator
    {
        yield 'completed state' => [PaymentInterface::STATE_COMPLETED];
        yield 'failed state' => [PaymentInterface::STATE_FAILED];
        yield 'cancelled state' => [PaymentInterface::STATE_CANCELLED];
        yield 'refunded state' => [PaymentInterface::STATE_REFUNDED];
        yield 'unknown state' => [PaymentInterface::STATE_UNKNOWN];
    }
}
