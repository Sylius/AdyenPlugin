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

use Sylius\Behat\Page\Admin\Crud\CreatePage as BaseCreatePage;

final class CreatePage extends BaseCreatePage implements CreatePageInterface
{
    public function setAdyenPlatform(string $platform): void
    {
        $this->getDocument()->selectFieldOption('Platform', $platform);
    }

    public function setValue(string $name, $value): void
    {
        $this->getElement($name)->setValue($value);
    }

    protected function getDefinedElements(): array
    {
        return parent::getDefinedElements() + [
            'apiKey' => '#sylius_payment_method_gatewayConfig_config_apiKey',
            'merchantAccount' => '#sylius_payment_method_gatewayConfig_config_merchantAccount',
            'hmacKey' => '#sylius_payment_method_gatewayConfig_config_hmacKey',
            'clientKey' => '#sylius_payment_method_gatewayConfig_config_clientKey',
            'authUser' => '#sylius_payment_method_gatewayConfig_config_authUser',
            'authPassword' => '#sylius_payment_method_gatewayConfig_config_authPassword',
        ];
    }
}
