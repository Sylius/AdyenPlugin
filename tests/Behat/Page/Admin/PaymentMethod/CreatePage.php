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
        $this->getElement('platform')->selectOption($platform);
    }

    public function setValue(string $name, $value): void
    {
        $this->getElement($name)->setValue($value);
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'platform' => '[data-test-environment]',
            'apiKey' => '[data-test-api-key]',
            'merchantAccount' => '[data-test-merchant-account]',
            'hmacKey' => '[data-test-hmac-key]',
            'clientKey' => '[data-test-client-key]',
            'authUser' => '[data-test-auth-user]',
            'authPassword' => '[data-test-auth-password]',
        ]);
    }
}
