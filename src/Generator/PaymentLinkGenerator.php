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
use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Entity\PaymentLinkInterface;
use Sylius\AdyenPlugin\Exception\PaymentLinkGenerationException;
use Sylius\AdyenPlugin\Factory\PaymentLinkFactoryInterface;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

final class PaymentLinkGenerator implements PaymentLinkGeneratorInterface
{
    public function __construct(
        private AdyenClientProviderInterface $adyenClientProvider,
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private PaymentLinkRepositoryInterface $paymentLinkRepository,
        private PaymentLinkFactoryInterface $paymentLinkFactory,
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private array $allowedPaymentStates = [PaymentInterface::STATE_NEW, PaymentInterface::STATE_PROCESSING],
    ) {
    }

    public function generate(PaymentInterface $payment): PaymentLinkInterface
    {
        if (!in_array($payment->getState(), $this->allowedPaymentStates, true)) {
            throw PaymentLinkGenerationException::create(
                $payment,
                sprintf('Cannot generate a payment link for payment with state: "%s".', $payment->getState()),
            );
        }

        /** @var PaymentMethodInterface|null $method */
        $method = $payment->getMethod();
        if (null === $method || !$this->adyenPaymentMethodChecker->isAdyenPaymentMethod($method)) {
            throw PaymentLinkGenerationException::create($payment, 'Payment method is not an Adyen one.');
        }

        $client = $this->adyenClientProvider->getForPaymentMethod($method);

        $this->expireAndRemoveOldLinks($client, $payment);

        try {
            $response = $client->generatePaymentLink($payment);
        } catch (\Exception $exception) {
            $this->logger->error('An error occurred during payment link generation.', [
                'paymentId' => $payment->getId(),
                'exception' => $exception,
            ]);

            throw PaymentLinkGenerationException::create(
                $payment,
                'Error: ' . $exception->getMessage(),
                previous: $exception,
            );
        }

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

        if ($this->stateMachine->can($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS)) {
            $this->entityManager->flush(); // Just to make sure it's saved before state change

            $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS);
        }

        $this->entityManager->flush();

        return $paymentLink;
    }

    private function expireAndRemoveOldLinks(AdyenClientInterface $adyenClient, PaymentInterface $payment): void
    {
        $paymentLinks = $this->paymentLinkRepository->findBy(['payment' => $payment]);

        foreach ($paymentLinks as $oldLink) {
            try {
                $adyenClient->expirePaymentLink($oldLink->getPaymentLinkId());
            } catch (\Exception) {
            }

            $this->entityManager->remove($oldLink);
        }

        $this->entityManager->flush();
    }
}
