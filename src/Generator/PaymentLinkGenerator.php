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

namespace Sylius\AdyenPlugin\Generator;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\AdyenPlugin\Exception\PaymentLinkGenerationException;
use Sylius\AdyenPlugin\Factory\PaymentLinkFactoryInterface;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class PaymentLinkGenerator implements PaymentLinkGeneratorInterface
{
    public function __construct(
        private AdyenClientProviderInterface $adyenClientProvider,
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private PaymentLinkFactoryInterface $paymentLinkFactory,
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function generate(PaymentInterface $payment): PaymentLinkInterface
    {
        if (PaymentInterface::STATE_NEW !== $payment->getState()) {
            throw PaymentLinkGenerationException::create($payment, 'Payment is not in a new state.');
        }

        /** @var PaymentMethodInterface|null $method */
        $method = $payment->getMethod();
        if (null === $method || !$this->adyenPaymentMethodChecker->isAdyenPaymentMethod($method)) {
            throw PaymentLinkGenerationException::create($payment, 'Payment method is not an Adyen one.');
        }

        $client = $this->adyenClientProvider->getForPaymentMethod($method);
        $response = $client->generatePaymentLink($payment);

        if ($response['status'] !== 'active' || !isset($response['url'], $response['id'])) {
            $this->logger->error('Failed to generate payment link.', [
                'paymentId' => $payment->getId(),
                'response' => $response,
            ]);

            throw PaymentLinkGenerationException::create($payment, 'Adyen API did not return a valid payment link.');
        }

        $payment->setDetails($response);
        $paymentLink = $this->paymentLinkFactory->create($payment, $response['id'], $response['url']);

        $this->entityManager->persist($paymentLink);
        $this->entityManager->flush(); // Just to make sure it's saved before state change

        $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS);

        $this->entityManager->flush();

        return $paymentLink;
    }
}
