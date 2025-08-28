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
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SuccessfulResponseProcessor extends AbstractProcessor
{
    public const THANKS_ROUTE_NAME = 'sylius_shop_order_thank_you';

    public const PAYMENT_PROCEED_CODES = ['authorised'];

    public const ORDER_ID_KEY = 'sylius_order_id';

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        TranslatorInterface $translator,
        private readonly MessageBusInterface $messageBus,
        private readonly PaymentCommandFactoryInterface $paymentCommandFactory,
    ) {
        parent::__construct($urlGenerator, $translator);
    }

    public function accepts(Request $request, ?PaymentInterface $payment): bool
    {
        return $this->isResultCodeSupportedForPayment($payment, self::PAYMENT_PROCEED_CODES);
    }

    public function process(
        string $code,
        Request $request,
        PaymentInterface $payment,
    ): string {
        $targetRoute = self::THANKS_ROUTE_NAME;

        $paymentStatusReceivedCommand = $this->paymentCommandFactory->createForEvent(self::PAYMENT_STATUS_RECEIVED_CODE, $payment);
        $this->messageBus->dispatch($paymentStatusReceivedCommand);

        $request->getSession()->set(self::ORDER_ID_KEY, $payment->getOrder()->getId());

        return $this->generateUrl($targetRoute, $request, $payment);
    }
}
