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

namespace Tests\Sylius\AdyenPlugin\Unit\Validator;

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\AdyenPlugin\Repository\Query\AdyenPaymentMethodQueryInterface;
use Sylius\AdyenPlugin\Validator\Constraint\ProvinceAddressConstraintValidatorDecorator;
use Sylius\Bundle\AddressingBundle\Validator\Constraints\ProvinceAddressConstraint;
use Sylius\Bundle\AddressingBundle\Validator\Constraints\ProvinceAddressConstraintValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Context\CartNotFoundException;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\Sylius\AdyenPlugin\Unit\AddressMother;

class ProvinceAddressConstraintValidatorDecoratorTest extends ConstraintValidatorTestCase
{
    /** @var mixed|\PHPUnit\Framework\MockObject\MockObject|ProvinceAddressConstraintValidator */
    private $decorated;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(ProvinceAddressConstraintValidator::class);

        parent::setUp();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ProvinceAddressConstraintValidatorDecorator($this->decorated);
    }

    public function testNonRelatedCountry(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createShippingAddress();

        $this->validator->validate($address, $constraint);
        $this->assertNoViolation();
    }

    public function testRelatedCountryAndEmptyProvince(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('US');

        $this->validator->validate($address, $constraint);
        $this->buildViolation($constraint->message)
            ->assertRaised()
        ;
    }

    public static function provideTestRelatedCountryAndEmptyProvinceWithAlreadyViolatedConstraint(): array
    {
        $constraint = new ProvinceAddressConstraint();

        return [
            'with foreign constraint' => ['some foreign constraint', 2],
            'with decorated constraint' => [$constraint->message, 1],
        ];
    }

    #[DataProvider('provideTestRelatedCountryAndEmptyProvinceWithAlreadyViolatedConstraint')]
    public function testRelatedCountryAndEmptyProvinceWithAlreadyViolatedConstraint(
        string $violationMessage,
        int $expectedCount,
    ): void {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('US');
        $this->context->addViolation($violationMessage);

        $this->validator->validate($address, $constraint);
        $this->assertCount($expectedCount, $this->context->getViolations());
    }

    public function testRelatedCountryAndEmptyProvinceWithProvidedDependenciesWhenNoAdyenMethodIsEnabled(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('US');

        $channel = $this->createMock(ChannelInterface::class);
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $cartContext = $this->createMock(CartContextInterface::class);

        $adyenPaymentMethodQuery = $this->createMock(AdyenPaymentMethodQueryInterface::class);
        $adyenPaymentMethodQuery
            ->method('findAllAdyenByChannel')
            ->with($channel)
            ->willReturn([])
        ;

        $validator = $this->createValidatorWithDependencies(
            $channelContext,
            $adyenPaymentMethodQuery,
            null,
            $cartContext,
        );
        $validator->validate($address, $constraint);

        $this->assertNoViolation();
    }

    public function testRelatedCountryAndEmptyProvinceWithProvidedDependenciesWhenAdyenMethodIsEnabled(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('US');

        $channel = $this->createMock(ChannelInterface::class);
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $cartContext = $this->createMock(CartContextInterface::class);

        $enabledPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $enabledPaymentMethod->method('isEnabled')->willReturn(true);

        $adyenPaymentMethodQuery = $this->createMock(AdyenPaymentMethodQueryInterface::class);
        $adyenPaymentMethodQuery
            ->method('findAllAdyenByChannel')
            ->with($channel)
            ->willReturn([$enabledPaymentMethod])
        ;

        $validator = $this->createValidatorWithDependencies(
            $channelContext,
            $adyenPaymentMethodQuery,
            null,
            $cartContext,
        );
        $validator->validate($address, $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised()
        ;
    }

    public function testFallbackToCartChannelWhenChannelNotFoundAndAdyenMethodEnabled(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('US');

        $channel = $this->createMock(ChannelInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $order->method('getChannel')->willReturn($channel);

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext
            ->method('getChannel')
            ->willThrowException(new ChannelNotFoundException())
        ;

        $cartContext = $this->createMock(CartContextInterface::class);
        $cartContext->method('getCart')->willReturn($order);

        $enabledPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $enabledPaymentMethod->method('isEnabled')->willReturn(true);

        $adyenPaymentMethodQuery = $this->createMock(AdyenPaymentMethodQueryInterface::class);
        $adyenPaymentMethodQuery
            ->method('findAllAdyenByChannel')
            ->with($channel)
            ->willReturn([$enabledPaymentMethod])
        ;

        $validator = $this->createValidatorWithDependencies(
            $channelContext,
            $adyenPaymentMethodQuery,
            null,
            $cartContext,
        );
        $validator->validate($address, $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised()
        ;
    }

    public function testFallbackToCartWhenNoChannelAvailableDoesNotAddViolation(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('US');

        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext
            ->method('getChannel')
            ->willThrowException(new ChannelNotFoundException())
        ;

        $cartContext = $this->createMock(CartContextInterface::class);
        $cartContext
            ->method('getCart')
            ->willThrowException(new CartNotFoundException())
        ;

        $adyenPaymentMethodQuery = $this->createMock(AdyenPaymentMethodQueryInterface::class);

        $validator = $this->createValidatorWithDependencies(
            $channelContext,
            $adyenPaymentMethodQuery,
            null,
            $cartContext,
        );
        $validator->validate($address, $constraint);

        $this->assertNoViolation();
    }

    public function testRelatedCountryAndEmptyProvinceWithProvidedDependenciesWhenAllMethodsDisabled(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('US');

        $channel = $this->createMock(ChannelInterface::class);
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $cartContext = $this->createMock(CartContextInterface::class);

        $disabledPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $disabledPaymentMethod->method('isEnabled')->willReturn(false);

        $adyenPaymentMethodQuery = $this->createMock(AdyenPaymentMethodQueryInterface::class);
        $adyenPaymentMethodQuery
            ->method('findAllAdyenByChannel')
            ->with($channel)
            ->willReturn([$disabledPaymentMethod])
        ;

        $validator = $this->createValidatorWithDependencies(
            $channelContext,
            $adyenPaymentMethodQuery,
            null,
            $cartContext,
        );
        $validator->validate($address, $constraint);

        $this->assertNoViolation();
    }

    public function testRelatedCountryWithProvinceWhenAdyenMethodIsEnabled(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('US');
        $address->setProvinceCode('US-TX');

        $channel = $this->createMock(ChannelInterface::class);
        $channelContext = $this->createMock(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willReturn($channel);

        $cartContext = $this->createMock(CartContextInterface::class);

        $enabledPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $enabledPaymentMethod->method('isEnabled')->willReturn(true);

        $adyenPaymentMethodQuery = $this->createMock(AdyenPaymentMethodQueryInterface::class);
        $adyenPaymentMethodQuery
            ->method('findAllAdyenByChannel')
            ->with($channel)
            ->willReturn([$enabledPaymentMethod])
        ;

        $validator = $this->createValidatorWithDependencies(
            $channelContext,
            $adyenPaymentMethodQuery,
            null,
            $cartContext,
        );
        $validator->validate($address, $constraint);

        $this->assertNoViolation();
    }

    public function testCustomRequiredCountryListAddsViolation(): void
    {
        $constraint = new ProvinceAddressConstraint();
        $address = AddressMother::createAddressWithSpecifiedCountryAndEmptyProvince('PL');

        $validator = $this->createValidatorWithDependencies(
            null,
            null,
            ['PL'],
        );
        $validator->validate($address, $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised()
        ;
    }

    private function createValidatorWithDependencies(
        ?ChannelContextInterface $channelContext,
        ?AdyenPaymentMethodQueryInterface $adyenPaymentMethodQuery,
        ?array $countryList = null,
        ?CartContextInterface $cartContext = null,
    ): ProvinceAddressConstraintValidatorDecorator {
        $this->validator = new ProvinceAddressConstraintValidatorDecorator(
            $this->decorated,
            $countryList ?? ProvinceAddressConstraintValidatorDecorator::PROVINCE_REQUIRED_COUNTRIES_DEFAULT_LIST,
            $channelContext,
            $adyenPaymentMethodQuery,
            $cartContext,
        );

        $this->validator->initialize($this->context);

        return $this->validator;
    }
}
