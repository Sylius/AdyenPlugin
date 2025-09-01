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

namespace Sylius\AdyenPlugin\Provider;

use Sylius\AdyenPlugin\Client\AdyenClient;
use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Sylius\AdyenPlugin\Client\AdyenTransportFactory;
use Sylius\AdyenPlugin\Client\ClientPayloadFactoryInterface;
use Sylius\AdyenPlugin\Exception\NonAdyenPaymentMethodException;
use Sylius\AdyenPlugin\Exception\UnprocessablePaymentException;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Resource\Exception\UpdateHandlingException;

final class AdyenClientProvider implements AdyenClientProviderInterface
{
    use GatewayConfigFromPaymentTrait;

    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly AdyenTransportFactory $adyenTransportFactory,
        private readonly ClientPayloadFactoryInterface $clientPayloadFactory,
    ) {
    }

    public function getDefaultClient(): AdyenClientInterface
    {
        $paymentMethod = $this->paymentMethodRepository->findOneByChannel(
            $this->channelContext->getChannel(),
        );

        if (null === $paymentMethod) {
            throw new UpdateHandlingException('No Adyen provider is configured');
        }

        $config = $this->getGatewayConfig($paymentMethod)->getConfig();

        return new AdyenClient(
            $config,
            $this->adyenTransportFactory,
            $this->clientPayloadFactory,
        );
    }

    public function getForPaymentMethod(PaymentMethodInterface $paymentMethod): AdyenClientInterface
    {
        $gatewayConfig = $this->getGatewayConfig($paymentMethod);
        $isAdyen = isset($gatewayConfig->getConfig()[self::FACTORY_NAME]);
        if (!$isAdyen) {
            throw new NonAdyenPaymentMethodException($paymentMethod);
        }

        return new AdyenClient(
            $gatewayConfig->getConfig(),
            $this->adyenTransportFactory,
            $this->clientPayloadFactory,
        );
    }

    public function getClientForCode(string $code): AdyenClientInterface
    {
        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($code);

        if (null === $paymentMethod) {
            throw new UnprocessablePaymentException();
        }

        return $this->getForPaymentMethod($paymentMethod);
    }
}
