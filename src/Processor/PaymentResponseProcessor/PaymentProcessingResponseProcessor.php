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

final class PaymentProcessingResponseProcessor extends AbstractProcessor
{
    use ProcessableResponseTrait;

    public const PAYMENT_PROCESSING_CODES = ['received', 'processing'];

    public const LABEL_PROCESSING = 'sylius_adyen.ui.payment_processing';

    public const REDIRECT_TARGET_ROUTE = 'sylius_shop_homepage';

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
        return $this->isResultCodeSupportedForPayment($payment, self::PAYMENT_PROCESSING_CODES);
    }

    public function process(
        string $code,
        Request $request,
        PaymentInterface $payment,
    ): string {
        $this->dispatchPaymentStatusReceived($payment);
        $this->addFlash($request, self::FLASH_INFO, self::LABEL_PROCESSING);

        return $this->urlGenerator->generate(self::REDIRECT_TARGET_ROUTE);
    }
}
