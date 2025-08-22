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

namespace Sylius\AdyenPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Factory\PaymentLinkFactoryInterface;
use Sylius\AdyenPlugin\PaymentGraph;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GeneratePayLinkAction
{
    public function __construct(
        private AdyenClientProviderInterface $adyenClientProvider,
        private PaymentRepositoryInterface $paymentRepository,
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private PaymentLinkFactoryInterface $paymentLinkFactory,
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        /** @var PaymentInterface|null $payment */
        $payment = $this->paymentRepository->find($id);
        if (null === $payment) {
            throw new NotFoundHttpException(sprintf('Payment with ID %s not found.', $id));
        }
        if (PaymentInterface::STATE_NEW !== $payment->getState()) {
            throw new \RuntimeException('Generating payment links is only possible for new payments.');
        }

        /** @var PaymentMethodInterface|null $method */
        $method = $payment->getMethod();
        if (null === $method || !$this->adyenPaymentMethodChecker->isAdyenPaymentMethod($method)) {
            throw new \RuntimeException('Generating payment links is only possible for Adyen payments.');
        }

        try {
            $client = $this->adyenClientProvider->getForPaymentMethod($method);
            $response = $client->generatePaymentLink($payment);

            if ($response['status'] !== 'active' || !isset($response['url'], $response['id'])) {
                $this->logger->error('Failed to generate payment link.', [
                    'paymentId' => $id,
                    'response' => $response,
                ]);

                throw new \RuntimeException('Failed to generate a valid payment link.');
            }

            $payment->setDetails($response);
            $paymentLink = $this->paymentLinkFactory->create($payment, $response['id']);

            $this->entityManager->persist($paymentLink);
            $this->entityManager->flush(); // Just to make sure it's saved before state change

            $this->stateMachine->apply($payment, PaymentGraph::GRAPH, PaymentGraph::TRANSITION_PROCESS);

            $this->entityManager->flush();

            $this->addFlash('success', 'sylius_adyen.payment_link.generation_success', [
                '%url%' => $response['url'],
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'sylius_adyen.payment_link.generation_fail');

            throw new \RuntimeException('Failed to generate payment link: ' . $e->getMessage(), previous: $e);
        }

        return new RedirectResponse(
            $this->urlGenerator->generate('sylius_admin_order_show', [
                'id' => $payment->getOrder()->getId(),
            ]),
        );
    }

    private function addFlash(string $type, string $message, array $parameters = []): void
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->requestStack->getSession()->getBag('flashes');
        $flashBag->add($type, [
            'message' => $message,
            'parameters' => $parameters,
        ]);
    }
}
