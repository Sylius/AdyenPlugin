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

namespace Sylius\AdyenPlugin\Checker;

use Doctrine\Persistence\ObjectManager;
use Sylius\AdyenPlugin\Exception\CheckoutValidationException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OrderCheckoutCompleteIntegrityChecker implements OrderCheckoutCompleteIntegrityCheckerInterface
{
    public function __construct(
        private readonly OrderProcessorInterface $orderProcessor,
        private readonly ObjectManager $orderManager,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly array $validationGroups = ['sylius_checkout_complete'],
    ) {
    }

    public function check(OrderInterface $order): void
    {
        $constraintViolationList = $this->validator->validate(value: $order, groups: $this->validationGroups);
        if (0 < $constraintViolationList->count()) {
            $messages = [];
            foreach ($constraintViolationList as $violation) {
                $messages[] = $violation->getMessage();
            }

            throw new CheckoutValidationException(implode(', ', $messages));
        }

        $oldTotal = $order->getTotal();
        $this->orderProcessor->process($order);
        if ($oldTotal !== $order->getTotal()) {
            $this->orderManager->flush();

            throw new CheckoutValidationException($this->translator->trans('sylius_adyen.runtime.order_total_changed'));
        }
    }
}
