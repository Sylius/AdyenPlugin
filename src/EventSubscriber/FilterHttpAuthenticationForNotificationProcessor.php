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

namespace Sylius\AdyenPlugin\EventSubscriber;

use Psr\Log\LoggerInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Traits\GatewayConfigFromPaymentTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class FilterHttpAuthenticationForNotificationProcessor implements EventSubscriberInterface
{
    use GatewayConfigFromPaymentTrait;

    public const ROUTE_NAME = 'sylius_adyen_shop_process_notifications';

    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'filterAuthentication',
        ];
    }

    public function filterAuthentication(RequestEvent $requestEvent): void
    {
        $request = $requestEvent->getRequest();
        if (self::ROUTE_NAME !== $request->attributes->get('_route')) {
            return;
        }

        $paymentMethodCode = (string) $request->attributes->get('paymentMethodCode');

        if ($this->isAuthenticated($request, $this->getConfiguration($paymentMethodCode))) {
            return;
        }

        throw new HttpException(
            Response::HTTP_FORBIDDEN,
            'Forbidden: Invalid credentials provided for webhook authentication.',
        );
    }

    private function getConfiguration(string $code): array
    {
        $paymentMethod = $this->paymentMethodRepository->getOneAdyenForCode($code);
        if (null === $paymentMethod) {
            throw new NotFoundHttpException();
        }

        return $this->getGatewayConfig($paymentMethod)->getConfig();
    }

    private function isAuthenticated(Request $request, array $configuration): bool
    {
        if (!isset($configuration['authUser']) && !isset($configuration['authPassword'])) {
            return true;
        }

        /** @var string $authUser */
        $authUser = $configuration['authUser'];

        /** @var string $authPassword */
        $authPassword = $configuration['authPassword'];

        if (
            \hash_equals($request->getUser() ?? '', $authUser) &&
            \hash_equals($request->getPassword() ?? '', $authPassword)
        ) {
            return true;
        }

        $this->logger->error(\sprintf(
            'Webhook authentication failed. Check the provided credentials: [%s] [%s]',
            $request->getUser() ?? '',
            $request->getPassword() ?? '',
        ));

        return false;
    }
}
