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

final class AddOrderPaymentWorkflowTransitionPass extends AbstractWorkflowTransitionPass
{
    protected function getWorkflowDefinitionId(): string
    {
        return 'state_machine.sylius_order_payment.definition';
    }

    protected function getRequiredTransitions(): array
    {
        return [
            ['name' => 'request_payment', 'from' => 'paid', 'to' => 'awaiting_payment'],
            ['name' => 'cancel', 'from' => 'paid', 'to' => 'cancelled'],
        ];
    }
}
