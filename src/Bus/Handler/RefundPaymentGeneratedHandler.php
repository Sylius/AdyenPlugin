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

namespace Sylius\AdyenPlugin\Bus\Handler;

use Sylius\AdyenPlugin\Bus\Command\CreateReferenceForRefund;
use Sylius\AdyenPlugin\Bus\DispatcherInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Repository\PaymentRepositoryInterface;
use Sylius\AdyenPlugin\Repository\RefundPaymentRepositoryInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\RefundPlugin\Event\RefundPaymentGenerated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final class RefundPaymentGeneratedHandler
{
    use GatewayConfigFromPaymentTrait;

    /** @var AdyenClientProviderInterface */
    private $adyenClientProvider;

    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    /** @var PaymentRepositoryInterface */
    private $paymentRepository;

    /** @var RefundPaymentRepositoryInterface */
    private $refundPaymentRepository;

    /** @var DispatcherInterface */
    private $dispatcher;

    public function __construct(
        AdyenClientProviderInterface $adyenClientProvider,
        PaymentRepositoryInterface $paymentRepository,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        RefundPaymentRepositoryInterface $refundPaymentRepository,
        DispatcherInterface $dispatcher,
    ) {
        $this->adyenClientProvider = $adyenClientProvider;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentRepository = $paymentRepository;
        $this->refundPaymentRepository = $refundPaymentRepository;
        $this->dispatcher = $dispatcher;
    }

    private function createReference(
        string $newReference,
        RefundPaymentGenerated $refundPaymentGenerated,
        PaymentInterface $payment,
    ): void {
        $refund = $this->refundPaymentRepository->find($refundPaymentGenerated->id());
        if (null === $refund) {
            return;
        }

        $this->dispatcher->dispatch(new CreateReferenceForRefund($newReference, $refund, $payment));
    }

    private function sendRefundRequest(
        RefundPaymentGenerated $refundPaymentGenerated,
        PaymentMethodInterface $paymentMethod,
        PaymentInterface $payment,
    ): string {
        Assert::keyExists(
            $payment->getDetails(),
            'pspReference',
            'Payment has not been initialized by Adyen',
        );

        $client = $this->adyenClientProvider->getForPaymentMethod($paymentMethod);

        $result = $client->requestRefund(
            $payment,
            $refundPaymentGenerated,
        );

        Assert::keyExists($result, 'pspReference');

        return (string) $result['pspReference'];
    }

    public function __invoke(RefundPaymentGenerated $refundPaymentGenerated): void
    {
        $payment = $this->paymentRepository->find($refundPaymentGenerated->paymentId());
        $paymentMethod = $this->paymentMethodRepository->find($refundPaymentGenerated->paymentMethodId());

        if (null === $payment ||
            null === $paymentMethod ||
            !isset($this->getGatewayConfig($paymentMethod)->getConfig()[AdyenClientProviderInterface::FACTORY_NAME])
        ) {
            return;
        }

        $adyenReference = $this->sendRefundRequest($refundPaymentGenerated, $paymentMethod, $payment);
        $this->createReference($adyenReference, $refundPaymentGenerated, $payment);
    }
}
