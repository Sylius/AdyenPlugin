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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FallbackResponseProcessor extends AbstractProcessor
{
    public const REDIRECT_TARGET_ACTION = 'sylius_adyen_shop_thank_you';

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator, TranslatorInterface $translator)
    {
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
    }

    public function accepts(Request $request, ?PaymentInterface $payment): bool
    {
        return null !== $payment;
    }

    public function process(
        string $code,
        Request $request,
        PaymentInterface $payment,
    ): string {
        $tokenValue = $request->query->get('tokenValue');
        if (null === $tokenValue) {
            $this->setActiveOrderViaPayment($request, $payment);
        }

        return $this->urlGenerator->generate(
            self::REDIRECT_TARGET_ACTION,
            [
                'code' => $code,
                'tokenValue' => $tokenValue,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function setActiveOrderViaPayment(Request $request, PaymentInterface $payment): void
    {
        $order = $payment->getOrder();
        if (null === $order) {
            return;
        }

        $request->getSession()->set('sylius_order_id', $order->getId());
    }
}
