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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Validator\EsdValidator;

final class EsdValidatorTest extends TestCase
{
    /** @var EsdValidator */
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new EsdValidator();
    }

    #[Test]
    public function it_validates_valid_level2_data(): void
    {
        $validData = [
            'customerReference' => 'CUST123',
            'totalTaxAmount' => 100,
            'destinationCountryCode' => 'US',
            'destinationPostalCode' => '12345',
        ];

        $errors = $this->validator->validateLevel2Data($validData);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_detects_missing_required_level2_fields(): void
    {
        $invalidData = [
            'totalTaxAmount' => 100,
        ];

        $errors = $this->validator->validateLevel2Data($invalidData);

        $this->assertNotEmpty($errors);
        $this->assertContains('Required field "customerReference" is missing or empty', $errors);
    }

    #[Test]
    public function it_validates_customer_reference_length(): void
    {
        $invalidData = [
            'customerReference' => str_repeat('A', 18), // Too long
            'totalTaxAmount' => 100,
        ];

        $errors = $this->validator->validateLevel2Data($invalidData);

        $this->assertContains('Customer reference must not exceed 17 characters', $errors);
    }

    #[Test]
    public function it_validates_country_code_format(): void
    {
        $invalidData = [
            'customerReference' => 'CUST123',
            'totalTaxAmount' => 100,
            'destinationCountryCode' => 'USA', // Should be 2 letters
        ];

        $errors = $this->validator->validateLevel2Data($invalidData);

        $this->assertContains('Destination country code must be a valid 2-letter ISO country code', $errors);
    }

    #[Test]
    public function it_validates_numeric_fields(): void
    {
        $invalidData = [
            'customerReference' => 'CUST123',
            'totalTaxAmount' => 'invalid', // Should be numeric
        ];

        $errors = $this->validator->validateLevel2Data($invalidData);

        $this->assertContains('Total tax amount must be a valid positive number', $errors);
    }

    #[Test]
    public function it_validates_valid_level3_data(): void
    {
        $validData = [
            'customerReference' => 'CUST123',
            'totalTaxAmount' => 100,
            'itemDetailLine' => [
                [
                    'productCode' => 'PROD001',
                    'description' => 'Test Product',
                    'quantity' => 2,
                    'unitOfMeasure' => 'PCS',
                    'commodityCode' => '12345678',
                    'totalAmount' => 200,
                    'unitPrice' => 100,
                ],
            ],
        ];

        $errors = $this->validator->validateLevel3Data($validData);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_validates_missing_item_detail_line_array(): void
    {
        $invalidData = [
            'customerReference' => 'CUST123',
            'totalTaxAmount' => 100,
        ];

        $errors = $this->validator->validateLevel3Data($invalidData);

        $this->assertContains('Level 3 data must include itemDetailLine array', $errors);
    }

    #[Test]
    public function it_validates_item_description_length(): void
    {
        $invalidData = [
            'customerReference' => 'CUST123',
            'totalTaxAmount' => 100,
            'itemDetailLine' => [
                [
                    'productCode' => 'PROD001',
                    'description' => str_repeat('A', 27), // Too long
                    'quantity' => 2,
                    'unitOfMeasure' => 'PCS',
                    'commodityCode' => '12345678',
                    'totalAmount' => 200,
                    'unitPrice' => 100,
                ],
            ],
        ];

        $errors = $this->validator->validateLevel3Data($invalidData);

        $this->assertContains('Item 1 description must not exceed 26 characters', $errors);
    }

    #[Test]
    public function it_validates_commodity_code_format(): void
    {
        $invalidData = [
            'customerReference' => 'CUST123',
            'totalTaxAmount' => 100,
            'itemDetailLine' => [
                [
                    'productCode' => 'PROD001',
                    'description' => 'Test Product',
                    'quantity' => 2,
                    'unitOfMeasure' => 'PCS',
                    'commodityCode' => '123', // Too short
                    'totalAmount' => 200,
                    'unitPrice' => 100,
                ],
            ],
        ];

        $errors = $this->validator->validateLevel3Data($invalidData);

        $this->assertContains('Item 1 commodity code must be 8-12 digits', $errors);
    }

    #[Test]
    public function it_validates_airline_data(): void
    {
        $validData = [
            'enhancedSchemeData.customerReference' => 'CUST123',
            'enhancedSchemeData.totalTaxAmount' => '100',
            'enhancedSchemeData.airline.leg1.departureAirport' => 'LAX',
            'enhancedSchemeData.airline.leg1.arrivalAirport' => 'JFK',
            'enhancedSchemeData.airline.leg1.flightNumber' => 'AA123',
            'enhancedSchemeData.airline.leg1.departureDate' => '20230101',
        ];

        $errors = $this->validator->validateAirlineData($validData);

        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_validates_airport_code_format(): void
    {
        $invalidData = [
            'enhancedSchemeData.customerReference' => 'CUST123',
            'enhancedSchemeData.totalTaxAmount' => '100',
            'enhancedSchemeData.airline.leg1.departureAirport' => 'LAXX', // Should be 3 letters
            'enhancedSchemeData.airline.leg1.arrivalAirport' => 'JFK',
            'enhancedSchemeData.airline.leg1.flightNumber' => 'AA123',
            'enhancedSchemeData.airline.leg1.departureDate' => '20230101',
        ];

        $errors = $this->validator->validateAirlineData($invalidData);

        $this->assertContains('Leg 1 departure airport must be a valid 3-letter IATA code', $errors);
    }

    #[Test]
    public function it_validates_date_format(): void
    {
        $invalidData = [
            'enhancedSchemeData.customerReference' => 'CUST123',
            'enhancedSchemeData.totalTaxAmount' => '100',
            'enhancedSchemeData.airline.leg1.departureAirport' => 'LAX',
            'enhancedSchemeData.airline.leg1.arrivalAirport' => 'JFK',
            'enhancedSchemeData.airline.leg1.flightNumber' => 'AA123',
            'enhancedSchemeData.airline.leg1.departureDate' => '2023-01-01', // Should be YYYYMMDD
        ];

        $errors = $this->validator->validateAirlineData($invalidData);

        $this->assertContains('Leg 1 departure date must be in YYYYMMDD format', $errors);
    }
}
