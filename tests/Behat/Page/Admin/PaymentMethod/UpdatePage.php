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

namespace Tests\Sylius\AdyenPlugin\Behat\Page\Admin\PaymentMethod;

use Sylius\Behat\Page\Admin\PaymentMethod\UpdatePage as BaseUpdatePage;

final class UpdatePage extends BaseUpdatePage implements UpdatePageInterface
{
    public function getElementValue(string $name): string
    {
        return $this->getElement($name)->getValue();
    }

    protected function getDefinedElements(): array
    {
        return parent::getDefinedElements() + [
            'apiKey' => '#sylius_payment_method_gatewayConfig_config_apiKey',
            'merchantAccount' => '#sylius_payment_method_gatewayConfig_config_merchantAccount',
            'hmacKey' => '#sylius_payment_method_gatewayConfig_config_hmacKey',
        ];
    }
}
