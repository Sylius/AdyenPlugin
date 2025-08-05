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

namespace Tests\Sylius\AdyenPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Sylius\AdyenPlugin\Form\Type\CredentialType;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Tests\Sylius\AdyenPlugin\Behat\Page\Admin\PaymentMethod\UpdatePageInterface;
use Webmozart\Assert\Assert;

class AdyenPaymentMethodConfigurationContext implements Context
{
    /** @var UpdatePageInterface */
    private $updatePage;

    public function __construct(
        UpdatePageInterface $updatePage,
    ) {
        $this->updatePage = $updatePage;
    }

    /**
     * @Then I want fields :fieldNames to be filled as placeholder
     */
    public function iWantAFieldToBeFilledAsPlaceholder(string $fieldNames): void
    {
        $fieldNames = explode(',', $fieldNames);
        foreach ($fieldNames as $fieldName) {
            $fieldName = trim($fieldName);
            Assert::eq($this->updatePage->getElementValue($fieldName), CredentialType::CREDENTIAL_PLACEHOLDER);
        }
    }

    /**
     * @Then I want the payment method :paymentMethod configuration to be:
     *
     * @param \Sylius\Component\Core\Model\PaymentMethodInterface $paymentMethod
     */
    public function iWantThePaymentMethodConfigurationToBe(TableNode $table, PaymentMethodInterface $paymentMethod)
    {
        foreach ($table->getHash() as $row) {
            Assert::eq($paymentMethod->getGatewayConfig()->getConfig()[$row['name']], $row['value']);
        }
    }
}
