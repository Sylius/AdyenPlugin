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

use Sylius\AdyenPlugin\Bus\PaymentCommandFactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
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

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        TranslatorInterface $translator,
        private readonly MessageBusInterface $messageBus,
        private readonly PaymentCommandFactoryInterface $paymentCommandFactory,
    ) {
        $this->translator = $translator;
    }

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

        $paymentStatusReceivedCommand = $this->paymentCommandFactory->createForEvent(self::PAYMENT_STATUS_RECEIVED_CODE, $payment);
        $this->messageBus->dispatch($paymentStatusReceivedCommand);

        $order = $payment->getOrder();
        Assert::notNull($order);

        return $this->getRedirectUrl($order);
    }

    private function getRedirectUrl(OrderInterface $order): string
    {
        $tokenValue = $order->getTokenValue();

        if (null === $tokenValue) {
            return $this->urlGenerator->generate(self::CHECKOUT_FINALIZATION_REDIRECT);
        }

        return $this->urlGenerator->generate(
            self::FAILURE_REDIRECT_TARGET,
            ['tokenValue' => $order->getTokenValue()],
        );
    }
}
