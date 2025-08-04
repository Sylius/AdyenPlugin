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

use Adyen\AdyenException;
use Adyen\Service;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\MinkContext;
use FriendsOfBehat\PageObjectExtension\Page\UnexpectedPageException;
use Sylius\Behat\Service\Resolver\CurrentPageResolverInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Sylius\AdyenPlugin\Behat\Page\Admin\PaymentMethod\CreatePageInterface;
use Tests\Sylius\AdyenPlugin\Behat\Page\Admin\PaymentMethod\UpdatePage;

final class ManagingPaymentMethodAdyenContext extends MinkContext implements Context
{
    /** @var CurrentPageResolverInterface */
    private $currentPageResolver;

    /** @var CreatePageInterface */
    private $createPage;

    /** @var KernelInterface */
    private $kernel;

    /** @var UpdatePage */
    private $updatePage;

    public function __construct(
        CurrentPageResolverInterface $currentPageResolver,
        CreatePageInterface $createPage,
        UpdatePage $updatePage,
        KernelInterface $kernel,
    ) {
        $this->createPage = $createPage;
        $this->currentPageResolver = $currentPageResolver;
        $this->kernel = $kernel;
        $this->updatePage = $updatePage;
    }

    /**
     * @Given I want to create a new Adyen payment method
     * @Given I open
     *
     * @throws UnexpectedPageException
     */
    public function iWantToCreateANewAdyenPaymentMethod(): void
    {
        $this->createPage->open(['factory' => 'adyen']);
    }

    /**
     * @Given Adyen service will confirm merchantAccount :merchantAccount and apiKey :apiKey are valid
     */
    public function adyenServiceWillConfirmMerchantAccountAndApiKeyAreValid(string $merchantAccount, string $apiKey): void
    {
        $this->kernel
            ->getContainer()
            ->get('tests.sylius_adyen.behat.context.api_mock_client')
            ->setJsonHandler(function (
                Service $service,
                string $url,
                array $payload,
            ) use ($merchantAccount, $apiKey) {
                $config = $service->getClient()->getConfig();

                if ($config->getXApiKey() !== $apiKey) {
                    throw new AdyenException('', Response::HTTP_UNAUTHORIZED);
                }

                if ($payload['merchantAccount'] !== $merchantAccount) {
                    throw new AdyenException('', Response::HTTP_FORBIDDEN);
                }

                return [];
            });
    }

    /**
     * @When /^I specify test configuration with:$/
     */
    public function iSpecifyTestConfigurationWithMerchantAccountAndApiKey(TableNode $formValues): void
    {
        $this->resolveCurrentPage()->setAdyenPlatform('test');

        $hash = $formValues->getHash();
        foreach ($hash as $row) {
            $this->resolveCurrentPage()->setValue($row['name'], $row['value']);
        }
    }

    /**
     * @return CreatePageInterface|UpdatePage
     */
    private function resolveCurrentPage()
    {
        return $this->currentPageResolver->getCurrentPageWithForm([
            $this->createPage, $this->updatePage,
        ]);
    }
}
