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

namespace Sylius\AdyenPlugin\Validator;

final class EsdValidator implements EsdValidatorInterface
{
    public function validateLevel2Data(array $esd): array
    {
        $errors = [];

        // Required Level 2 fields
        if (!isset($esd['customerReference']) || $esd['customerReference'] === '') {
            $errors[] = 'Required field "customerReference" is missing or empty';
        } elseif (strlen($esd['customerReference']) > 17) {
            $errors[] = 'Customer reference must not exceed 17 characters';
        } elseif (!preg_match('/^[A-Za-z0-9\s\-]+$/', $esd['customerReference'])) {
            $errors[] = 'Customer reference contains invalid characters';
        }

        if (!isset($esd['totalTaxAmount'])) {
            $errors[] = 'Required field "totalTaxAmount" is missing';
        } elseif (!is_numeric($esd['totalTaxAmount']) || $esd['totalTaxAmount'] < 0) {
            $errors[] = 'Total tax amount must be a valid positive number';
        }

        // Optional fields validation
        if (isset($esd['destinationCountryCode'])) {
            if (!preg_match('/^[A-Z]{2}$/', $esd['destinationCountryCode'])) {
                $errors[] = 'Destination country code must be a valid 2-letter ISO country code';
            }
        }

        if (isset($esd['destinationPostalCode'])) {
            if (strlen($esd['destinationPostalCode']) > 10) {
                $errors[] = 'Destination postal code must not exceed 10 characters';
            }
        }

        return $errors;
    }

    public function validateLevel3Data(array $esd): array
    {
        $errors = $this->validateLevel2Data($esd);

        // Validate itemDetailLine array
        if (!isset($esd['itemDetailLine']) || !is_array($esd['itemDetailLine'])) {
            $errors[] = 'Level 3 data must include itemDetailLine array';

            return $errors;
        }

        foreach ($esd['itemDetailLine'] as $index => $item) {
            $itemNumber = $index + 1;

            $requiredItemFields = ['productCode', 'description', 'quantity', 'unitOfMeasure', 'commodityCode', 'totalAmount', 'unitPrice'];
            foreach ($requiredItemFields as $field) {
                if (!isset($item[$field]) || $item[$field] === '') {
                    $errors[] = sprintf('Required field "%s" is missing or empty for item %d', $field, $itemNumber);
                }
            }

            if (isset($item['description']) && strlen($item['description']) > 26) {
                $errors[] = sprintf('Item %d description must not exceed 26 characters', $itemNumber);
            }

            if (isset($item['productCode']) && strlen($item['productCode']) > 12) {
                $errors[] = sprintf('Item %d product code must not exceed 12 characters', $itemNumber);
            }

            if (isset($item['quantity']) && (!is_numeric($item['quantity']) || $item['quantity'] <= 0)) {
                $errors[] = sprintf('Item %d quantity must be a positive number', $itemNumber);
            }

            if (isset($item['unitPrice']) && (!is_numeric($item['unitPrice']) || $item['unitPrice'] < 0)) {
                $errors[] = sprintf('Item %d unit price must be a valid positive number', $itemNumber);
            }

            if (isset($item['totalAmount']) && (!is_numeric($item['totalAmount']) || $item['totalAmount'] < 0)) {
                $errors[] = sprintf('Item %d total amount must be a valid positive number', $itemNumber);
            }

            if (isset($item['commodityCode']) && !preg_match('/^\d{8,12}$/', $item['commodityCode'])) {
                $errors[] = sprintf('Item %d commodity code must be 8-12 digits', $itemNumber);
            }
        }

        return $errors;
    }

    public function validateAirlineData(array $esd): array
    {
        $errors = [];

        $requiredFields = [
            'enhancedSchemeData.customerReference',
            'enhancedSchemeData.totalTaxAmount',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($esd[$field]) || $esd[$field] === '') {
                $errors[] = sprintf('Required field "%s" is missing or empty', $field);
            }
        }

        for ($i = 1; $i <= 4; ++$i) {
            $legField = "enhancedSchemeData.airline.leg{$i}.departureAirport";
            if (!isset($esd[$legField])) {
                continue;
            }

            $requiredLegFields = [
                "enhancedSchemeData.airline.leg{$i}.arrivalAirport",
                "enhancedSchemeData.airline.leg{$i}.flightNumber",
                "enhancedSchemeData.airline.leg{$i}.departureDate",
            ];

            foreach ($requiredLegFields as $field) {
                if (!isset($esd[$field]) || $esd[$field] === '') {
                    $errors[] = sprintf('Required field "%s" is missing or empty', $field);
                }
            }

            if (isset($esd["enhancedSchemeData.airline.leg{$i}.departureAirport"])) {
                $airport = $esd["enhancedSchemeData.airline.leg{$i}.departureAirport"];
                if (!preg_match('/^[A-Z]{3}$/', $airport)) {
                    $errors[] = sprintf('Leg %d departure airport must be a valid 3-letter IATA code', $i);
                }
            }

            if (isset($esd["enhancedSchemeData.airline.leg{$i}.arrivalAirport"])) {
                $airport = $esd["enhancedSchemeData.airline.leg{$i}.arrivalAirport"];
                if (!preg_match('/^[A-Z]{3}$/', $airport)) {
                    $errors[] = sprintf('Leg %d arrival airport must be a valid 3-letter IATA code', $i);
                }
            }

            if (isset($esd["enhancedSchemeData.airline.leg{$i}.departureDate"])) {
                $date = $esd["enhancedSchemeData.airline.leg{$i}.departureDate"];
                if (!preg_match('/^\d{8}$/', $date)) {
                    $errors[] = sprintf('Leg %d departure date must be in YYYYMMDD format', $i);
                }
            }
        }

        return $errors;
    }

    public function validateLodgingData(array $esd): array
    {
        $errors = [];

        $requiredFields = [
            'enhancedSchemeData.customerReference',
            'enhancedSchemeData.totalTaxAmount',
            'enhancedSchemeData.lodging.checkInDate',
            'enhancedSchemeData.lodging.checkOutDate',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($esd[$field]) || $esd[$field] === '') {
                $errors[] = sprintf('Required field "%s" is missing or empty', $field);
            }
        }

        if (isset($esd['enhancedSchemeData.lodging.checkInDate'])) {
            $checkInDate = $esd['enhancedSchemeData.lodging.checkInDate'];
            if (!preg_match('/^\d{8}$/', $checkInDate)) {
                $errors[] = 'Check-in date must be in YYYYMMDD format';
            }
        }

        if (isset($esd['enhancedSchemeData.lodging.checkOutDate'])) {
            $checkOutDate = $esd['enhancedSchemeData.lodging.checkOutDate'];
            if (!preg_match('/^\d{8}$/', $checkOutDate)) {
                $errors[] = 'Check-out date must be in YYYYMMDD format';
            }
        }

        if (isset($esd['enhancedSchemeData.lodging.numberOfRoomRates'])) {
            $numberOfRooms = $esd['enhancedSchemeData.lodging.numberOfRoomRates'];
            if (!is_numeric($numberOfRooms) || $numberOfRooms <= 0) {
                $errors[] = 'Number of room rates must be a positive number';
            }
        }

        return $errors;
    }

    public function validateCarRentalData(array $esd): array
    {
        $errors = [];

        $requiredFields = [
            'enhancedSchemeData.customerReference',
            'enhancedSchemeData.totalTaxAmount',
            'enhancedSchemeData.carRental.rentalAgreementNumber',
            'enhancedSchemeData.carRental.renterName',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($esd[$field]) || $esd[$field] === '') {
                $errors[] = sprintf('Required field "%s" is missing or empty', $field);
            }
        }

        if (isset($esd['enhancedSchemeData.carRental.pickUpDate'])) {
            $pickUpDate = $esd['enhancedSchemeData.carRental.pickUpDate'];
            if (!preg_match('/^\d{8}$/', $pickUpDate)) {
                $errors[] = 'Pick-up date must be in YYYYMMDD format';
            }
        }

        if (isset($esd['enhancedSchemeData.carRental.returnDate'])) {
            $returnDate = $esd['enhancedSchemeData.carRental.returnDate'];
            if (!preg_match('/^\d{8}$/', $returnDate)) {
                $errors[] = 'Return date must be in YYYYMMDD format';
            }
        }

        if (isset($esd['enhancedSchemeData.carRental.renterName'])) {
            $renterName = $esd['enhancedSchemeData.carRental.renterName'];
            if (strlen($renterName) > 26) {
                $errors[] = 'Renter name must not exceed 26 characters';
            }
        }

        return $errors;
    }
}
