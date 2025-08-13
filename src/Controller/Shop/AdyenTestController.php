<?php

namespace Sylius\AdyenPlugin\Controller\Shop;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class AdyenTestController
{
    public function __construct(private Environment $twig) {}

    public function __invoke(string $orderToken): Response
    {
        $html = $this->twig->render('adyen/test_dropin.html.twig', [
            'orderToken' => $orderToken,
            'gatewayCode' => 'adyen_code',
        ]);

        return new Response($html);
    }
}
