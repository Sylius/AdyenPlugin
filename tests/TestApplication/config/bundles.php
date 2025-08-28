<?php

declare(strict_types=1);

use Sylius\AdyenPlugin\SyliusAdyenPlugin;
use Sylius\RefundPlugin\SyliusRefundPlugin;
use Knp\Bundle\SnappyBundle\KnpSnappyBundle;

$bundles = [
    SyliusAdyenPlugin::class => ['all' => true],
    SyliusRefundPlugin::class => ['all' => true],
    KnpSnappyBundle::class => ['all' => true],
];

return $bundles;
