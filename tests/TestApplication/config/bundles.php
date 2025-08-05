<?php

declare(strict_types=1);

$bundles = [
    Sylius\AdyenPlugin\SyliusAdyenPlugin::class => ['all' => true],
    Sylius\RefundPlugin\SyliusRefundPlugin::class => ['all' => true],
    Knp\Bundle\SnappyBundle\KnpSnappyBundle::class => ['all' => true],
];

return $bundles;
