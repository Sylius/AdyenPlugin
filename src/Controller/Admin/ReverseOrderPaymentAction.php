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

use Sylius\AdyenPlugin\Checker\AdyenPaymentMethodCheckerInterface;
use Sylius\AdyenPlugin\Processor\Order\ReverseOrderPaymentProcessor;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ReverseOrderPaymentAction
{
    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private AdyenPaymentMethodCheckerInterface $adyenPaymentMethodChecker,
        private ReverseOrderPaymentProcessor $reverseOrderPaymentProcessor,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(mixed $id, mixed $paymentId, Request $request): Response
    {
        /** @var PaymentInterface|null $payment */
        $payment = $this->paymentRepository->find($paymentId);
        if (null === $payment) {
            throw new NotFoundHttpException(sprintf('Payment with ID %s not found.', $id));
        }

        $order = $payment->getOrder();
        if (null === $order || (string) $order->getId() !== $id) {
            throw new NotFoundHttpException(sprintf('Payment with ID %s not found for order with ID %s.', $paymentId, $id));
        }

        if (!$this->adyenPaymentMethodChecker->isAdyenPayment($payment)) {
            throw new NotFoundHttpException(sprintf('Payment with ID %s is not an Adyen payment.', $id));
        }

        $this->reverseOrderPaymentProcessor->process($order);

        $this->addSuccessFlash();

        return new RedirectResponse(
            $this->urlGenerator->generate('sylius_admin_order_show', [
                'id' => $order->getId(),
            ]),
        );
    }

    private function addSuccessFlash(): void
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->requestStack->getSession()->getBag('flashes');
        $flashBag->add('success', [
            'message' => 'sylius.resource.update',
            'parameters' => ['%resource%' => 'Order'],
        ]);
    }
}
