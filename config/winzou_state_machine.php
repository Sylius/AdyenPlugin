<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use winzou\Bundle\StateMachineBundle\winzouStateMachineBundle;

return static function (ContainerConfigurator $configurator): void {
    if (class_exists(winzouStateMachineBundle::class)) {
        $configurator->import('winzou_state_machine/*.yaml');
    }
};
