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

use Sylius\AdyenPlugin\Bus\DispatcherInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SuccessfulResponseProcessor extends AbstractProcessor
{
    use ProcessableResponseTrait;

    public const MY_ORDERS_ROUTE_NAME = 'sylius_shop_account_order_index';

    public const THANKS_ROUTE_NAME = 'sylius_shop_order_thank_you';

    public const PAYMENT_PROCEED_CODES = ['authorised'];

    public const ORDER_ID_KEY = 'sylius_order_id';

    public const TOKEN_VALUE_KEY = 'tokenValue';

    public const LABEL_PAYMENT_COMPLETED = 'sylius.payment.completed';

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(
        DispatcherInterface $dispatcher,
        UrlGeneratorInterface $urlGenerator,
        TranslatorInterface $translator,
    ) {
        $this->dispatcher = $dispatcher;
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
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

        $this->dispatchPaymentStatusReceived($payment);

        if ($this->shouldTheAlternativeThanksPageBeShown($request)) {
            $this->addFlash($request, self::FLASH_INFO, self::LABEL_PAYMENT_COMPLETED);
            $targetRoute = self::MY_ORDERS_ROUTE_NAME;
        }

        return $this->urlGenerator->generate($targetRoute);
    }

    private function shouldTheAlternativeThanksPageBeShown(Request $request): bool
    {
        if (null !== $request->query->get(self::TOKEN_VALUE_KEY)) {
            return true;
        }

        if (null !== $request->getSession()->get(self::ORDER_ID_KEY)) {
            return false;
        }

        return true;
    }
}
