<?php

declare(strict_types=1);

use Sylius\AdyenPlugin\SyliusAdyenPlugin;
use Sylius\RefundPlugin\SyliusRefundPlugin;
use Knp\Bundle\SnappyBundle\KnpSnappyBundle;
use winzou\Bundle\StateMachineBundle\winzouStateMachineBundle;

$bundles = [
    SyliusAdyenPlugin::class => ['all' => true],
    SyliusRefundPlugin::class => ['all' => true],
    KnpSnappyBundle::class => ['all' => true],
    winzouStateMachineBundle::class => ['all' => true],
];

return $bundles;
