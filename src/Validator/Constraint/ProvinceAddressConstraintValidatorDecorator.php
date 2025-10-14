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

namespace Sylius\AdyenPlugin\Validator\Constraint;

use Sylius\AdyenPlugin\Repository\Query\AdyenPaymentMethodQueryInterface;
use Sylius\Bundle\AddressingBundle\Validator\Constraints\ProvinceAddressConstraint;
use Sylius\Bundle\AddressingBundle\Validator\Constraints\ProvinceAddressConstraintValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

class ProvinceAddressConstraintValidatorDecorator extends ConstraintValidator
{
    public const PROVINCE_REQUIRED_COUNTRIES_DEFAULT_LIST = [
        'CA', 'US',
    ];

    public function __construct(
        private readonly ProvinceAddressConstraintValidator $decorated,
        /** @var array|string[] */
        private readonly array $provinceRequiredCountriesList = self::PROVINCE_REQUIRED_COUNTRIES_DEFAULT_LIST,
        private readonly ?ChannelContextInterface $channelContext = null,
        private readonly ?AdyenPaymentMethodQueryInterface $adyenPaymentMethodQuery = null,
        private readonly ?CartContextInterface $cartContext = null,
    ) {
    }

    /**
     * @param AddressInterface|mixed $value
     * @param ProvinceAddressConstraint|Constraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $this->decorated->initialize($this->context);
        $this->decorated->validate($value, $constraint);

        if (!$this->isAnyAdyenMethodAvailable()) {
            return;
        }

        Assert::isInstanceOf($value, AddressInterface::class);
        Assert::isInstanceOf($constraint, ProvinceAddressConstraint::class);

        if ($this->hasViolation($constraint)) {
            return;
        }

        if (!in_array((string) $value->getCountryCode(), $this->provinceRequiredCountriesList, true)) {
            return;
        }

        if (null !== $value->getProvinceCode() || null !== $value->getProvinceName()) {
            return;
        }

        $this->context->addViolation($constraint->message);
    }

    private function hasViolation(Constraint $constraint): bool
    {
        Assert::isInstanceOf($constraint, ProvinceAddressConstraint::class);

        foreach ($this->context->getViolations() as $violation) {
            if ($violation->getMessageTemplate() === $constraint->message) {
                return true;
            }
        }

        return false;
    }

    private function isAnyAdyenMethodAvailable(): bool
    {
        if (
            null === $this->channelContext ||
            null === $this->adyenPaymentMethodQuery ||
            null === $this->cartContext
        ) {
            return true;
        }

        $channel = $this->resolveChannel();
        if (null === $channel) {
            return false;
        }

        $paymentMethods = $this->adyenPaymentMethodQuery->findAllAdyenByChannel($channel);
        foreach ($paymentMethods as $key => $paymentMethod) {
            if (!$paymentMethod->isEnabled()) {
                unset($paymentMethods[$key]);
            }
        }

        return 0 !== count($paymentMethods);
    }

    private function resolveChannel(): ?ChannelInterface
    {
        try {
            return $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            try {
                /** @var OrderInterface $cart */
                $cart = $this->cartContext->getCart();

                return $cart->getChannel();
            } catch (CartNotFoundException) {
            }
        }

        return null;
    }
}
