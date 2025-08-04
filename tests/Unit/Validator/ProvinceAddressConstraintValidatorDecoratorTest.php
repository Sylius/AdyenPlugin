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
use Sylius\AdyenPlugin\Validator\Constraint\ProvinceAddressConstraintValidatorDecorator;
use Sylius\Bundle\AddressingBundle\Validator\Constraints\ProvinceAddressConstraint;
use Sylius\Bundle\AddressingBundle\Validator\Constraints\ProvinceAddressConstraintValidator;
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
}
