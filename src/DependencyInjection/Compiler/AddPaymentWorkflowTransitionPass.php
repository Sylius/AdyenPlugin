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

namespace Sylius\AdyenPlugin\DependencyInjection\Compiler;

final class AddPaymentWorkflowTransitionPass extends AbstractWorkflowTransitionPass
{
    protected function getWorkflowDefinitionId(): string
    {
        return 'state_machine.sylius_payment.definition';
    }

    protected function getRequiredTransitions(): array
    {
        return [
            ['name' => 'process', 'from' => 'authorized', 'to' => 'processing'],
            ['name' => 'fail', 'from' => 'completed', 'to' => 'failed'],
            ['name' => 'cancel', 'from' => 'processing_reversal', 'to' => 'cancelled'],
            ['name' => 'refund', 'from' => 'processing_reversal', 'to' => 'refunded'],
        ];
    }
}
