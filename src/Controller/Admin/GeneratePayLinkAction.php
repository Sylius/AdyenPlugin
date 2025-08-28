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

use Sylius\AdyenPlugin\Email\Sender\PaymentLinkEmailSenderInterface;
use Sylius\AdyenPlugin\Exception\PaymentLinkGenerationException;
use Sylius\AdyenPlugin\Generator\PaymentLinkGeneratorInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class GeneratePayLinkAction
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly PaymentLinkGeneratorInterface $paymentLinkGenerator,
        private readonly PaymentLinkEmailSenderInterface $paymentLinkEmailSender,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        /** @var PaymentInterface|null $payment */
        $payment = $this->paymentRepository->find($id);
        if (null === $payment) {
            throw new NotFoundHttpException(sprintf('Payment with ID %s not found.', $id));
        }

        try {
            $recipient = $payment->getOrder()?->getCustomer()?->getEmail();
            Assert::stringNotEmpty($recipient, 'Cannot generate payment link for payment without customer email.');

            $paymentLink = $this->paymentLinkGenerator->generate($payment);

            $this->paymentLinkEmailSender->send($paymentLink, $recipient);

            $this->addFlash('success', 'sylius_adyen.payment_link.generation_success');
        } catch (PaymentLinkGenerationException) {
            $this->addFlash('error', 'sylius_adyen.payment_link.generation_fail');
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
