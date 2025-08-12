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

namespace Sylius\AdyenPlugin\Collector;

use Sylius\AdyenPlugin\Checker\EsdCardPaymentSupportCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class CompositeEsdCollector implements CompositeEsdCollectorInterface
{
    /** @var array<string, EsdCollectorInterface> */
    private array $collectors;

    /**
     * @param \Traversable<string, EsdCollectorInterface> $collectors
     * @param array<string> $supportedCurrencies
     * @param array<string> $supportedCountries
     */
    public function __construct(
        \Traversable $collectors,
        private readonly array $supportedCurrencies,
        private readonly array $supportedCountries,
        private readonly EsdCardPaymentSupportCheckerInterface $esdCardPaymentSupportChecker,
    ) {
        $this->collectors = iterator_to_array($collectors);
    }

    public function collect(
        OrderInterface $order,
        array $gatewayConfig,
        array $payload,
        ?PaymentInterface $payment = null,
    ): array {
        if (!$this->shouldIncludeEsd($order, $gatewayConfig, $payload, $payment)) {
            return [];
        }

        return $this->findCollector($gatewayConfig)->collect($order);
    }

    public function shouldIncludeEsd(
        OrderInterface $order,
        array $gatewayConfig,
        array $payload,
        ?PaymentInterface $payment = null,
    ): bool {
        if (!isset($gatewayConfig['esdEnabled']) || !$gatewayConfig['esdEnabled']) {
            return false;
        }

        $currencyCode = $order->getCurrencyCode();
        if (!in_array($currencyCode, $this->supportedCurrencies, true)) {
            return false;
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null || !in_array($billingAddress->getCountryCode(), $this->supportedCountries, true)) {
            return false;
        }

        if (!$this->esdCardPaymentSupportChecker->isSupported($payload, $payment)) {
            return false;
        }

        return true;
    }

    private function findCollector(array $gatewayConfig): EsdCollectorInterface
    {
        if (isset($gatewayConfig['esdType'], $this->collectors[$gatewayConfig['esdType']])) {
            return $this->collectors[$gatewayConfig['esdType']];
        }

        $merchantCategoryCode = $gatewayConfig['merchantCategoryCode'] ?? null;
        if (null !== $merchantCategoryCode) {
            foreach ($this->collectors as $collector) {
                if ($collector->supports($merchantCategoryCode)) {
                    return $collector;
                }
            }
        }

        if (isset($this->collectors['level3'])) {
            return $this->collectors['level3'];
        }

        if (isset($this->collectors['level2'])) {
            return $this->collectors['level2'];
        }

        throw new \RuntimeException('No ESD collector found');
    }
}
