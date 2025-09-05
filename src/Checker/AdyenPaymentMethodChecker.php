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

namespace Sylius\AdyenPlugin\Checker;

use Sylius\AdyenPlugin\Entity\AdyenPaymentDetailInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentLinkRepositoryInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Webmozart\Assert\Assert;

final class AdyenPaymentMethodChecker implements AdyenPaymentMethodCheckerInterface
{
    /** @param RepositoryInterface<AdyenPaymentDetailInterface> $adyenPaymentDetailRepository */
    public function __construct(
        private RepositoryInterface $adyenPaymentDetailRepository,
        private PaymentLinkRepositoryInterface $paymentLinkRepository,
    ) {
    }

    public function isAdyenPayment(PaymentInterface $payment): bool
    {
        /** @var PaymentMethodInterface|null $method */
        $method = $payment->getMethod();
        if (null === $method) {
            return false;
        }

        return $this->isAdyenPaymentMethod($method);
    }

    public function isAdyenPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $gatewayConfig = $paymentMethod->getGatewayConfig();
        if (null === $gatewayConfig) {
            return false;
        }

        $factoryName = $gatewayConfig->getConfig()['factory_name'] ?? $gatewayConfig->getFactoryName();

        return $factoryName === AdyenClientProviderInterface::FACTORY_NAME;
    }

    public function isCaptureMode(PaymentInterface $payment, string $mode): bool
    {
        $paymentDetail = $this->adyenPaymentDetailRepository->findOneBy(['payment' => $payment]);
        if (null !== $paymentDetail) {
            return $mode === $paymentDetail->getCaptureMode();
        }

        $paymentMethod = $payment->getMethod();
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return $mode === ($gatewayConfig?->getConfig()['captureMode'] ?? null);
    }

    public function isPayByLink(PaymentInterface $payment): bool
    {
        if (!$this->isAdyenPayment($payment)) {
            return false;
        }

        return 0 !== count($this->paymentLinkRepository->findBy(['payment' => $payment], limit: 1));
    }
}
