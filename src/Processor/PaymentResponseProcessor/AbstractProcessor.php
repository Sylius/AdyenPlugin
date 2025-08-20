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

namespace Sylius\AdyenPlugin\Processor\PaymentResponseProcessor;

use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractProcessor implements ProcessorInterface
{
    public const FLASH_INFO = 'info';

    public const FLASH_ERROR = 'error';

    public function __construct(
        protected readonly UrlGeneratorInterface $urlGenerator,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    protected function isResultCodeSupportedForPayment(?PaymentInterface $payment, array $resultCodes): bool
    {
        if (null === $payment) {
            return false;
        }

        $details = $payment->getDetails();
        if (!isset($details['resultCode'])) {
            return false;
        }

        return in_array(
            strtolower((string) $details['resultCode']),
            $resultCodes,
            true,
        );
    }

    protected function addFlash(
        Request $request,
        string $type,
        string $message,
        ?string $locale = null,
    ): void {
        $message = $this->translator->trans($message, locale: $locale ?? $this->getLocale($request));

        /** @var Session $session */
        $session = $request->getSession();

        $session->getFlashBag()->add($type, $message);
    }

    protected function generateUrl(
        string $targetRoute,
        Request $request,
        ?PaymentInterface $payment,
        array $parameters = [],
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        return $this->urlGenerator->generate(
            $targetRoute,
            array_merge($parameters, ['_locale' => $this->getLocale($request, $payment)]),
            $referenceType,
        );
    }

    private function getLocale(
        Request $request,
        ?PaymentInterface $payment = null,
    ): string {
        return $payment?->getOrder()?->getLocaleCode() ?? $request->getLocale();
    }
}
