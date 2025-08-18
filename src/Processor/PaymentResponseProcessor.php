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

namespace Sylius\AdyenPlugin\Processor;

use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\ProcessorInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PaymentResponseProcessor implements PaymentResponseProcessorInterface
{
    private const DEFAULT_REDIRECT_ROUTE = 'sylius_shop_order_thank_you';

    /** @var iterable<ProcessorInterface> */
    private $processors;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /**
     * @param iterable<ProcessorInterface> $processors
     */
    public function __construct(
        iterable $processors,
        UrlGeneratorInterface $urlGenerator,
    ) {
        $this->processors = $processors;
        $this->urlGenerator = $urlGenerator;
    }

    private function processForPaymentSpecified(
        string $code,
        Request $request,
        PaymentInterface $payment,
    ): ?string {
        foreach ($this->processors as $processor) {
            if (!$processor->accepts($request, $payment)) {
                continue;
            }

            return $processor->process($code, $request, $payment);
        }

        return null;
    }

    public function process(
        string $code,
        Request $request,
        ?PaymentInterface $payment,
    ): string {
        $result = null;
        if (null !== $payment) {
            $result = $this->processForPaymentSpecified($code, $request, $payment);
        }

        if (null !== $result) {
            return $result;
        }

        return $this->urlGenerator->generate(self::DEFAULT_REDIRECT_ROUTE, [
            '_locale' => $payment?->getOrder()?->getLocaleCode() ?? $request->getLocale(),
        ]);
    }
}
