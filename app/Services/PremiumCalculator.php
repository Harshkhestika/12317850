<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class PremiumCalculator
{
    /**
     * Base daily rate per plan code
     */
    public const BASE_RATES = [
        'BASIC' => 12.00,
        'PLUS' => 20.00,
        'PREMIUM' => 30.00,
    ];

    /**
     * Destination multipliers
     */
    public const DESTINATION_MULTIPLIERS = [
        'DOMESTIC' => 1.00,
        'INTERNATIONAL' => 1.80,
    ];

    /**
     * Sum insured / Coverage multipliers
     */
    public const COVERAGE_MULTIPLIERS = [
        100000 => 1.00,
        300000 => 1.25,
        500000 => 1.50,
    ];

    /**
     * Calculate insurance premium based on policy request details.
     *
     * Formula:
     * premium = base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier
     *
     * @param string $planCode BASIC, PLUS, PREMIUM
     * @param string|Carbon $tripStart Date of departure (YYYY-MM-DD)
     * @param string|Carbon $tripEnd Date of return (YYYY-MM-DD)
     * @param string|Carbon $dateOfBirth Date of birth (YYYY-MM-DD)
     * @param string $destination DOMESTIC or INTERNATIONAL
     * @param float|int|string $sumInsured 100000, 300000, or 500000
     * @return array Contains calculated premium, travel_days, age, and applied multipliers
     *
     * @throws InvalidArgumentException
     */
    public function calculate(
        string $planCode,
        string|Carbon $tripStart,
        string|Carbon $tripEnd,
        string|Carbon $dateOfBirth,
        string $destination,
        float|int|string $sumInsured
    ): array {
        $plan = strtoupper(trim($planCode));
        if (!isset(self::BASE_RATES[$plan])) {
            throw new InvalidArgumentException("Unsupported plan code: '{$planCode}'. Supported plans: " . implode(', ', array_keys(self::BASE_RATES)));
        }
        $baseRate = self::BASE_RATES[$plan];

        $start = $tripStart instanceof Carbon ? $tripStart->copy()->startOfDay() : Carbon::parse($tripStart)->startOfDay();
        $end = $tripEnd instanceof Carbon ? $tripEnd->copy()->startOfDay() : Carbon::parse($tripEnd)->startOfDay();
        $dob = $dateOfBirth instanceof Carbon ? $dateOfBirth->copy()->startOfDay() : Carbon::parse($dateOfBirth)->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException("Trip end date ({$end->toDateString()}) cannot be before trip start date ({$start->toDateString()}).");
        }

        // Travel days inclusive: trip_end - trip_start + 1; minimum 1
        $travelDays = (int) $start->diffInDays($end) + 1;
        if ($travelDays < 1) {
            $travelDays = 1;
        }

        // Age calculated on trip_start
        $ageOnTripStart = (int) $dob->diffInYears($start);
        $ageMultiplier = $this->getAgeMultiplier($ageOnTripStart);

        // Destination multiplier
        $dest = strtoupper(trim($destination));
        if (!isset(self::DESTINATION_MULTIPLIERS[$dest])) {
            throw new InvalidArgumentException("Unsupported destination: '{$destination}'. Supported destinations: " . implode(', ', array_keys(self::DESTINATION_MULTIPLIERS)));
        }
        $destMultiplier = self::DESTINATION_MULTIPLIERS[$dest];

        // Coverage multiplier
        $coverageInt = (int) round((float) $sumInsured);
        if (!isset(self::COVERAGE_MULTIPLIERS[$coverageInt])) {
            throw new InvalidArgumentException("Unsupported coverage sum_insured: '{$sumInsured}'. Supported values: 100000, 300000, 500000.");
        }
        $covMultiplier = self::COVERAGE_MULTIPLIERS[$coverageInt];

        // Formula: premium = base_daily_rate * travel_days * age_multiplier * destination_multiplier * coverage_multiplier
        // Using high precision decimal multiplication then rounded to 2 decimal places
        $rawPremium = (float) $baseRate * (float) $travelDays * (float) $ageMultiplier * (float) $destMultiplier * (float) $covMultiplier;
        $finalPremium = round($rawPremium, 2);

        return [
            'base_rate' => $baseRate,
            'travel_days' => $travelDays,
            'age_on_trip_start' => $ageOnTripStart,
            'age_multiplier' => $ageMultiplier,
            'destination_multiplier' => $destMultiplier,
            'coverage_multiplier' => $covMultiplier,
            'raw_premium' => $rawPremium,
            'premium' => $finalPremium,
        ];
    }

    /**
     * Determine age multiplier based on age at trip start.
     * 0-17: 1.10
     * 18-60: 1.00
     * 61-70: 1.50
     * 71+: 2.00
     */
    public function getAgeMultiplier(int $age): float
    {
        if ($age < 0) {
            throw new InvalidArgumentException("Invalid negative age calculated: {$age}. Check date of birth.");
        }

        if ($age <= 17) {
            return 1.10;
        }

        if ($age <= 60) {
            return 1.00;
        }

        if ($age <= 70) {
            return 1.50;
        }

        return 2.00;
    }
}
