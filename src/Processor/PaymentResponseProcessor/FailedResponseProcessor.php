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

use Sylius\AdyenPlugin\Bus\Command\FailPayment;
use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class FailedResponseProcessor extends AbstractProcessor
{
    public const PAYMENT_REFUSED_CODES = ['refused', 'rejected', 'cancelled', 'error'];

    public const CHECKOUT_FINALIZATION_REDIRECT = 'sylius_shop_checkout_complete';

    public const FAILURE_REDIRECT_TARGET = 'sylius_shop_order_show';

    public const LABEL_PAYMENT_FAILED = 'sylius_adyen.ui.payment_failed';

    public function accepts(Request $request, ?PaymentInterface $payment): bool
    {
        return $this->isResultCodeSupportedForPayment($payment, self::PAYMENT_REFUSED_CODES);
    }

    public function process(
        string $code,
        Request $request,
        PaymentInterface $payment,
    ): string {
        $this->addFlash($request, self::FLASH_ERROR, self::LABEL_PAYMENT_FAILED);

        return $this->getRedirectUrl($payment, $request);
    }

    private function getRedirectUrl(PaymentInterface $payment, Request $request): string
    {
        $order = $payment->getOrder();
        Assert::notNull($order);

        $tokenValue = $order->getTokenValue();

        if (null === $tokenValue) {
            return $this->generateUrl(self::CHECKOUT_FINALIZATION_REDIRECT, $request, $payment);
        }

        return $this->generateUrl(self::FAILURE_REDIRECT_TARGET, $request, $payment, [
            'tokenValue' => $tokenValue,
        ]);
    }
}
