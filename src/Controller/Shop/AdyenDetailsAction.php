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

use Sylius\AdyenPlugin\Resolver\Payment\PaymentDetailsResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdyenDetailsAction
{
    public const REFERENCE_ID_KEY = 'referenceId';

    /** @var PaymentDetailsResolverInterface */
    private $paymentDetailsResolver;

    public function __construct(
        PaymentDetailsResolverInterface $paymentDetailsResolver,
    ) {
        $this->paymentDetailsResolver = $paymentDetailsResolver;
    }

    public function __invoke(Request $request, string $code): Response
    {
        /** @var string|null $referenceId */
        $referenceId = $request->query->get(self::REFERENCE_ID_KEY);

        if (null === $referenceId) {
            return new Response('Reference ID is missing', Response::HTTP_BAD_REQUEST);
        }

        $payment = $this->paymentDetailsResolver->resolve($code, $referenceId);

        return new JsonResponse($payment->getDetails());
    }
}
