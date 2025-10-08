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

namespace Sylius\AdyenPlugin\Api\OpenApi;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use Sylius\Bundle\ApiBundle\OpenApi\Documentation\DocumentationModifierInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class AdyenDropinConfigurationDocumentationModifier implements DocumentationModifierInterface
{
    public function __construct(
        private string $shopApiRoute,
    ) {
    }

    public function modify(OpenApi $docs): OpenApi
    {
        $paths = $docs->getPaths();
        $schemas = $docs->getComponents()->getSchemas();

        $this->addShopDropinConfigurationPaths($paths);
        $schemas = $this->addShopDropinConfigurationSchema($schemas);

        return $docs
            ->withPaths($paths)
            ->withComponents($docs->getComponents()->withSchemas($schemas));
    }

    private function addShopDropinConfigurationSchema(array|\ArrayObject $schemas): array|\ArrayObject
    {
        $schemas['AdyenShopDropinConfiguration'] = [
            'type' => 'object',
            'properties' => [
                'billingAddress' => [
                    'type' => 'object',
                    'description' => 'The billing address of the shopper',
                    'properties' => [
                        'firstName' => ['type' => 'string'],
                        'lastName' => ['type' => 'string'],
                        'countryCode' => ['type' => 'string'],
                        'province' => ['type' => 'string'],
                        'city' => ['type' => 'string'],
                        'postcode' => ['type' => 'string'],
                    ],
                ],
                'paymentMethods' => [
                    'type' => 'object',
                    'description' => 'The available payment methods from Adyen',
                    'properties' => [
                        'paymentMethods' => [
                            'type' => 'array',
                            'items' => ['oneOf' => [
                                [
                                    'type' => 'object',
                                    'properties' => [
                                        'issuers' => [
                                            'type' => 'array',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'disabled' => ['type' => 'boolean'],
                                                    'id' => ['type' => 'string'],
                                                    'name' => ['type' => 'string'],
                                                ],
                                            ],
                                        ],
                                        'name' => ['type' => 'string'],
                                        'type' => ['type' => 'string'],
                                    ],
                                ],
                                [
                                    'type' => 'object',
                                    'properties' => [
                                        'brands' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                        ],
                                        'configuration' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'gatewayMerchantId' => ['type' => 'string'],
                                                'merchantId' => ['type' => 'string'],
                                                'merchantName' => ['type' => 'string'],
                                            ],
                                        ],
                                        'name' => ['type' => 'string'],
                                        'type' => ['type' => 'string'],
                                    ],
                                ],
                            ]],
                        ],
                        'storedPaymentMethods' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'string'],
                                    'type' => ['type' => 'string'],
                                    'brand' => ['type' => 'string'],
                                    'expiryMonth' => ['type' => 'string'],
                                    'expiryYear' => ['type' => 'string'],
                                    'lastFour' => ['type' => 'string'],
                                    'holderName' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'clientKey' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'environment' => ['type' => 'string'],
                'enableStoreDetails' => ['type' => 'boolean'],
                'amount' => [
                    'type' => 'object',
                    'properties' => [
                        'currency' => ['type' => 'string'],
                        'value' => ['type' => 'integer'],
                    ],
                ],
                'path' => [
                    'type' => 'object',
                    'properties' => [
                        'payments' => ['type' => 'string'],
                        'paymentDetails' => ['type' => 'string'],
                        'deleteToken' => ['type' => 'string'],
                    ],
                ],
                'translations' => [
                    'type' => 'object',
                    'properties' => [
                        'sylius_adyen.runtime.payment_failed_try_again' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        return $schemas;
    }

    private function addShopDropinConfigurationPaths(Paths $paths): void
    {
        $dropinItem = new PathItem(
            ref: 'AdyenShopDropinConfiguration',
            get: new Operation(
                operationId: 'sylius_adyen_api_shop_dropin_configuration',
                tags: ['Adyen'],
                responses: [
                    Response::HTTP_OK => [
                        'description' => 'Adyen Drop-in configuration',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/AdyenShopDropinConfiguration',
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Retrieve Adyen Drop-in configuration for the shop',
                description: 'Returns Adyen Drop-in configuration including payment methods, billing address, and other settings',
                parameters: [
                    new Parameter(
                        name: 'code',
                        in: 'path',
                        description: 'The code of the Adyen payment method',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                    new Parameter(
                        name: 'orderToken',
                        in: 'path',
                        description: 'The token of the order',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
        );

        $path = sprintf('%s/payment/adyen/configuration/{code}/{orderToken}', $this->shopApiRoute);
        $paths->addPath($path, $dropinItem);
    }
}
