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

use Sylius\Bundle\CoreBundle\SyliusCoreBundle;

final class AddOrderPaymentWorkflowTransitionPass extends AbstractWorkflowTransitionPass
{
    private const SYLIUS_VERSION_WITH_NATIVE_AUTHORIZED_TRANSITION = '2.2.7';

    protected function getWorkflowDefinitionId(): string
    {
        return 'state_machine.sylius_order_payment.definition';
    }

    protected function getRequiredTransitions(): array
    {
        $transitions = [
            ['name' => 'request_payment', 'from' => 'paid', 'to' => 'awaiting_payment'],
            ['name' => 'cancel', 'from' => 'paid', 'to' => 'cancelled'],
        ];

        if (version_compare(SyliusCoreBundle::VERSION, self::SYLIUS_VERSION_WITH_NATIVE_AUTHORIZED_TRANSITION, '<')) {
            $transitions[] = ['name' => 'request_payment', 'from' => 'authorized', 'to' => 'awaiting_payment'];
        }

        return $transitions;
    }
}
