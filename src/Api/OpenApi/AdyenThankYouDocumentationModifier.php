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

final readonly class AdyenThankYouDocumentationModifier implements DocumentationModifierInterface
{
    public function __construct(
        private string $shopApiRoute,
    ) {
    }

    public function modify(OpenApi $docs): OpenApi
    {
        $paths = $docs->getPaths();
        $schemas = $docs->getComponents()->getSchemas();

        $this->addCustomerThankYouPagePaths($paths);

        return $docs
            ->withPaths($paths)
            ->withComponents($docs->getComponents()->withSchemas($schemas))
        ;
    }

    private function addCustomerThankYouPagePaths(Paths $paths): void
    {
        $thankYouItem = new PathItem(
            ref: 'AdyenCustomerThankYouPage',
            get: new Operation(
                operationId: 'sylius_adyen_api_shop_custom_thank_you',
                tags: ['Adyen'],
                responses: [
                    Response::HTTP_OK => [
                        'description' => 'HTML Response with custom thank-you page',
                        'content' => [
                            'text/html' => [
                                'schema' => [
                                    'type' => 'string',
                                    'example' => '<html><body><h1>Thank you for your purchase!</h1></body></html>',
                                ],
                            ],
                        ],
                    ],
                ],
                summary: 'Custom thank-you page, that needs to be intercepted.',
                description: 'Returns a custom thank-you page HTML response that needs to be intercepted by the frontend',
                parameters: [
                    new Parameter(
                        name: 'code',
                        in: 'path',
                        description: 'The code of the Adyen payment method',
                        required: true,
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
        );

        $path = sprintf('%s/payment/adyen/thank-you/{code}', $this->shopApiRoute);
        $paths->addPath($path, $thankYouItem);
    }
}
