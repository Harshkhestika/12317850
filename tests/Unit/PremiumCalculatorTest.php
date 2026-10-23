<?php

namespace Tests\Unit;

use App\Services\PremiumCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PremiumCalculatorTest extends TestCase
{
    private PremiumCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PremiumCalculator();
    }

    /**
     * Test the exact example from the assignment specification:
     * travel_days = 5, age multiplier = 1.00, and premium = 20 * 5 * 1.00 * 1.80 * 1.25 = 225.00
     */
    public function test_assignment_specification_example(): void
    {
        $result = $this->calculator->calculate(
            planCode: 'PLUS',
            tripStart: '2026-11-10',
            tripEnd: '2026-11-14',
            dateOfBirth: '1998-04-10',
            destination: 'INTERNATIONAL',
            sumInsured: 300000
        );

        $this->assertEquals(5, $result['travel_days']);
        $this->assertEquals(20.00, $result['base_rate']);
        $this->assertEquals(1.00, $result['age_multiplier']);
        $this->assertEquals(1.80, $result['destination_multiplier']);
        $this->assertEquals(1.25, $result['coverage_multiplier']);
        $this->assertEquals(225.00, $result['premium']);
    }

    /**
     * Test all age brackets and boundaries.
     */
    public function test_age_bracket_multipliers(): void
    {
        // 0-17: 1.10
        $this->assertEquals(1.10, $this->calculator->getAgeMultiplier(0));
        $this->assertEquals(1.10, $this->calculator->getAgeMultiplier(17));

        // 18-60: 1.00
        $this->assertEquals(1.00, $this->calculator->getAgeMultiplier(18));
        $this->assertEquals(1.00, $this->calculator->getAgeMultiplier(40));
        $this->assertEquals(1.00, $this->calculator->getAgeMultiplier(60));

        // 61-70: 1.50
        $this->assertEquals(1.50, $this->calculator->getAgeMultiplier(61));
        $this->assertEquals(1.50, $this->calculator->getAgeMultiplier(70));

        // 71+: 2.00
        $this->assertEquals(2.00, $this->calculator->getAgeMultiplier(71));
        $this->assertEquals(2.00, $this->calculator->getAgeMultiplier(85));
    }

    /**
     * Test base daily rate per plan code.
     */
    public function test_plan_base_rates(): void
    {
        // BASIC = 12
        $basic = $this->calculator->calculate('BASIC', '2026-06-01', '2026-06-01', '2000-01-01', 'DOMESTIC', 100000);
        $this->assertEquals(12.00, $basic['premium']);

        // PLUS = 20
        $plus = $this->calculator->calculate('PLUS', '2026-06-01', '2026-06-01', '2000-01-01', 'DOMESTIC', 100000);
        $this->assertEquals(20.00, $plus['premium']);

        // PREMIUM = 30
        $premium = $this->calculator->calculate('PREMIUM', '2026-06-01', '2026-06-01', '2000-01-01', 'DOMESTIC', 100000);
        $this->assertEquals(30.00, $premium['premium']);
    }

    /**
     * Test destination multipliers.
     */
    public function test_destination_multipliers(): void
    {
        $domestic = $this->calculator->calculate('BASIC', '2026-06-01', '2026-06-01', '2000-01-01', 'DOMESTIC', 100000);
        $this->assertEquals(1.00, $domestic['destination_multiplier']);
        $this->assertEquals(12.00, $domestic['premium']);

        $intl = $this->calculator->calculate('BASIC', '2026-06-01', '2026-06-01', '2000-01-01', 'INTERNATIONAL', 100000);
        $this->assertEquals(1.80, $intl['destination_multiplier']);
        $this->assertEquals(21.60, $intl['premium']); // 12 * 1 * 1.0 * 1.80 * 1.0 = 21.60
    }

    /**
     * Test coverage multipliers.
     */
    public function test_coverage_multipliers(): void
    {
        $cov100k = $this->calculator->calculate('BASIC', '2026-06-01', '2026-06-01', '2000-01-01', 'DOMESTIC', 100000);
        $this->assertEquals(1.00, $cov100k['coverage_multiplier']);

        $cov300k = $this->calculator->calculate('BASIC', '2026-06-01', '2026-06-01', '2000-01-01', 'DOMESTIC', 300000);
        $this->assertEquals(1.25, $cov300k['coverage_multiplier']);
        $this->assertEquals(15.00, $cov300k['premium']); // 12 * 1 * 1 * 1 * 1.25 = 15.00

        $cov500k = $this->calculator->calculate('BASIC', '2026-06-01', '2026-06-01', '2000-01-01', 'DOMESTIC', 500000);
        $this->assertEquals(1.50, $cov500k['coverage_multiplier']);
        $this->assertEquals(18.00, $cov500k['premium']); // 12 * 1 * 1 * 1 * 1.50 = 18.00
    }

    /**
     * Test single-day travel is inclusive and minimum 1 day.
     */
    public function test_single_day_inclusive_travel(): void
    {
        $res = $this->calculator->calculate('BASIC', '2026-08-15', '2026-08-15', '2000-01-01', 'DOMESTIC', 100000);
        $this->assertEquals(1, $res['travel_days']);
    }

    /**
     * Test invalid dates (trip_end < trip_start) throws exception.
     */
    public function test_invalid_dates_throw_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate('BASIC', '2026-08-20', '2026-08-15', '2000-01-01', 'DOMESTIC', 100000);
    }

    /**
     * Test unsupported plan throws exception.
     */
    public function test_unsupported_plan_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate('ULTRA', '2026-08-15', '2026-08-16', '2000-01-01', 'DOMESTIC', 100000);
    }

    /**
     * Test unsupported coverage throws exception.
     */
    public function test_unsupported_coverage_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculate('BASIC', '2026-08-15', '2026-08-16', '2000-01-01', 'DOMESTIC', 200000);
    }
}
