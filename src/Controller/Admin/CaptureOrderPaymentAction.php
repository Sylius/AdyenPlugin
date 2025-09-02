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

use Psr\Log\LoggerInterface;
use Sylius\AdyenPlugin\Exception\PaymentActionException;
use Sylius\AdyenPlugin\Processor\Payment\ManualCaptureProcessorInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\PaymentRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CaptureOrderPaymentAction
{
    use FlashHelperTrait;

    /** @param PaymentRepositoryInterface<PaymentInterface> $paymentRepository */
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
        private readonly ManualCaptureProcessorInterface $manualCaptureProcessor,
        private readonly UrlGeneratorInterface $urlGenerator,
        RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
        $this->requestStack = $requestStack;
    }

    public function __invoke(mixed $id, mixed $paymentId, Request $request): Response
    {
        $payment = $this->paymentRepository->find($paymentId);
        if (null === $payment) {
            throw new NotFoundHttpException(sprintf('Payment with id %s has not been found.', $paymentId));
        }

        try {
            $this->manualCaptureProcessor->process($payment);
        } catch (PaymentActionException $exception) {
            $this->logger->error($exception->getMessage());

            $this->addFlash('error', 'sylius_adyen.payment.capture.error');
        }

        $this->addFlash('success', 'sylius_adyen.payment.capture.success');

        return new RedirectResponse($this->urlGenerator->generate('sylius_admin_order_show', ['id' => $id]));
    }
}
