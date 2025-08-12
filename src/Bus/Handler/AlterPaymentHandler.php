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

use Sylius\AdyenPlugin\Bus\Command\AlterPaymentCommand;
use Sylius\AdyenPlugin\Bus\Command\CancelPayment;
use Sylius\AdyenPlugin\Bus\Command\RequestCapture;
use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Webmozart\Assert\Assert;

#[AsMessageHandler]
final class AlterPaymentHandler
{
    use GatewayConfigFromPaymentTrait;

    public function __construct(private readonly AdyenClientProviderInterface $adyenClientProvider)
    {
    }

    public function __invoke(AlterPaymentCommand $alterPaymentCommand): void
    {
        $payment = $this->getPayment($alterPaymentCommand->getOrder());

        if (null === $payment || !$this->isAdyenPayment($payment)) {
            return;
        }

        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        $client = $this->adyenClientProvider->getForPaymentMethod($method);
        $this->dispatchRemoteAction($payment, $alterPaymentCommand, $client);
    }

    private function isCompleted(OrderInterface $order): bool
    {
        return PaymentInterface::STATE_COMPLETED === $order->getPaymentState();
    }

    private function isAdyenPayment(PaymentInterface $payment): bool
    {
        /** @var PaymentMethodInterface|null $method */
        $method = $payment->getMethod();
        if (
            null === $method?->getGatewayConfig() ||
            !isset($this->getGatewayConfig($method)->getConfig()[AdyenClientProviderInterface::FACTORY_NAME])
        ) {
            return false;
        }

        return true;
    }

    private function getPayment(OrderInterface $order): ?PaymentInterface
    {
        if ($this->isCompleted($order)) {
            return null;
        }

        $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);
        if (null === $payment) {
            return null;
        }

        return $payment;
    }

    private function dispatchRemoteAction(
        PaymentInterface $payment,
        AlterPaymentCommand $alterPaymentCommand,
        AdyenClientInterface $adyenClient,
    ): void {
        if ($alterPaymentCommand instanceof RequestCapture) {
            $adyenClient->requestCapture(
                $payment,
            );
        }

        if ($alterPaymentCommand instanceof CancelPayment) {
            $adyenClient->requestCancellation($payment);
        }
    }
}
