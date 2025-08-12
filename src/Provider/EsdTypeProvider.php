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

use Sylius\AdyenPlugin\Collector\EsdCollectorInterface;

final class EsdTypeProvider implements EsdTypeProviderInterface
{
    /** @var array<string, EsdCollectorInterface> */
    private array $collectors;

    /** @param \Traversable<string, EsdCollectorInterface> $collectors */
    public function __construct(\Traversable $collectors)
    {
        $this->collectors = iterator_to_array($collectors);
    }

    public function getAvailableTypes(): array
    {
        $types = [];

        foreach (array_keys($this->collectors) as $type) {
            $label = 'sylius_adyen.ui.esd_type_' . $type;
            $types[$label] = $type;
        }

        return $types;
    }
}
