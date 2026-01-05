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

final readonly class AdyenPaymentDetailsDocumentationModifier implements DocumentationModifierInterface
{
    public function __construct(
        private string $shopApiRoute,
    ) {
    }

    public function modify(OpenApi $docs): OpenApi
    {
        $paths = $docs->getPaths();
        $schemas = $docs->getComponents()->getSchemas();

        $this->addPaymentDetailsPaths($paths);
        $schemas = $this->addPaymentDetailsSchema($schemas);

        return $docs
            ->withPaths($paths)
            ->withComponents($docs->getComponents()->withSchemas($schemas))
        ;
    }

    private function addPaymentDetailsPaths(Paths $paths): void
    {
        $paymentDetailsItem = new PathItem(
            ref: 'AdyenPaymentDetails',
            get: new Operation(
                operationId: 'sylius_adyen_api_shop_adyen_details',
                tags: ['Adyen'],
                responses: [
                    Response::HTTP_OK => [
                        'description' => 'Adyen payment details',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/AdyenPaymentDetails',
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Adyen payment details',
                description: 'Retrieves payment details from Adyen for the specified payment code and reference ID',
                parameters: [
                    new Parameter(
                        name: 'code',
                        in: 'path',
                        description: 'The code of the Adyen payment method',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                    new Parameter(
                        name: 'referenceId',
                        in: 'query',
                        description: 'The reference ID of the payment',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
        );

        $path = sprintf('%s/payment/adyen/details/{code}', $this->shopApiRoute);
        $paths->addPath($path, $paymentDetailsItem);
    }

    /**
     * @param array<string, string[]|string>|\ArrayObject<string, string[]|string> $schemas
     *
     * @return array<string, string[]|string>|\ArrayObject<string, string[]|string>
     */
    private function addPaymentDetailsSchema(array|\ArrayObject $schemas): array|\ArrayObject
    {
        $schemas['AdyenPaymentDetails'] = [
            'type' => 'object',
            'properties' => [
                'pspReference' => ['type' => 'string'],
                'refusalReason' => ['type' => 'object'],
                'refusalCode' => ['type' => 'string'],
                'refusalReasonCode' => ['type' => 'string'],
                'amount' => [
                    'type' => 'object',
                    'properties' => [
                        'currency' => ['type' => 'string'],
                        'value' => ['type' => 'integer'],
                    ],
                ],
                'merchantReference' => ['type' => 'string'],
            ],
        ];

        return $schemas;
    }
}
