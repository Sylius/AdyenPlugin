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

namespace Sylius\AdyenPlugin\Controller\Shop;

use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor;
use Sylius\AdyenPlugin\Resolver\Payment\PaymentDetailsResolverInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectTargetAction
{
    public const REDIRECT_RESULT_KEY = 'redirectResult';

    public function __construct(
        private readonly PaymentResponseProcessor $paymentResponseProcessor,
        private readonly PaymentDetailsResolverInterface $paymentDetailsResolver,
    ) {
    }

    public function __invoke(Request $request, string $code): Response
    {
        $payment = $this->retrieveCurrentPayment($code, $request);

        return new RedirectResponse($this->paymentResponseProcessor->process($code, $request, $payment));
    }

    private function retrieveCurrentPayment(string $code, Request $request): ?PaymentInterface
    {
        $referenceId = $this->getReferenceId($request);

        if (null !== $referenceId) {
            return $this->paymentDetailsResolver->resolve($code, $referenceId);
        }

        return null;
    }

    private function getReferenceId(Request $request): ?string
    {
        return $request->query->has(self::REDIRECT_RESULT_KEY)
            ? (string) $request->query->get(self::REDIRECT_RESULT_KEY)
            : null
        ;
    }
}
