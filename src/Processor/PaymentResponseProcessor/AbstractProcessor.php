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
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractProcessor implements ProcessorInterface
{
    public const PAYMENT_STATUS_RECEIVED_CODE = 'payment_status_received';

    public const FLASH_INFO = 'info';

    public const FLASH_ERROR = 'error';

    protected ?TranslatorInterface $translator;

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
    ): void {
        if (null !== $this->translator) {
            $message = $this->translator->trans($message);
        }
        /** @var Session $session */
        $session = $request->getSession();

        $session->getFlashBag()->add($type, $message);
    }
}
