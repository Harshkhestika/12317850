<?php

namespace Database\Seeders;

use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Services\PremiumCalculator;
use Illuminate\Database\Seeder;

class PolicyRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $calculator = new PremiumCalculator();

        // Sample 1: Spec Example - VALIDATED
        $pricing1 = $calculator->calculate('PLUS', '2026-11-10', '2026-11-14', '1998-04-10', 'INTERNATIONAL', 300000);
        $p1 = PolicyRequest::firstOrCreate(
            ['agent_id' => 'AGENT-17', 'client_request_id' => 'AGT-2026-0001'],
            [
                'customer_name' => 'Sample Customer',
                'date_of_birth' => '1998-04-10',
                'destination' => 'INTERNATIONAL',
                'trip_start' => '2026-11-10',
                'trip_end' => '2026-11-14',
                'plan_code' => 'PLUS',
                'sum_insured' => 300000,
                'travel_days' => $pricing1['travel_days'],
                'premium' => $pricing1['premium'],
                'status' => PolicyRequest::STATE_VALIDATED,
            ]
        );
        if ($p1->wasRecentlyCreated) {
            $p1->stateHistories()->create([
                'from_state' => PolicyRequest::STATE_RECEIVED,
                'to_state' => PolicyRequest::STATE_VALIDATED,
                'notes' => 'Intake created and pricing validated. Premium: ₹225.00.',
            ]);
        }

        // Sample 2: Confirmed ISSUED Policy
        $pricing2 = $calculator->calculate('BASIC', '2026-07-01', '2026-07-10', '1990-05-15', 'DOMESTIC', 100000);
        $p2 = PolicyRequest::firstOrCreate(
            ['agent_id' => 'AGENT-05', 'client_request_id' => 'AGT-2026-0002'],
            [
                'customer_name' => 'Aarav Sharma',
                'date_of_birth' => '1990-05-15',
                'destination' => 'DOMESTIC',
                'trip_start' => '2026-07-01',
                'trip_end' => '2026-07-10',
                'plan_code' => 'BASIC',
                'sum_insured' => 100000,
                'travel_days' => $pricing2['travel_days'],
                'premium' => $pricing2['premium'],
                'status' => PolicyRequest::STATE_ISSUED,
                'policy_number' => 'POL-78291048',
                'provider_reference' => 'REF-99210482',
                'attempt_count' => 1,
            ]
        );
        if ($p2->wasRecentlyCreated) {
            $p2->stateHistories()->createMany([
                ['from_state' => PolicyRequest::STATE_RECEIVED, 'to_state' => PolicyRequest::STATE_VALIDATED, 'notes' => 'Policy validated.'],
                ['from_state' => PolicyRequest::STATE_VALIDATED, 'to_state' => PolicyRequest::STATE_SUBMITTED, 'notes' => 'Submitted to insurer.'],
                ['from_state' => PolicyRequest::STATE_SUBMITTED, 'to_state' => PolicyRequest::STATE_ISSUED, 'notes' => 'Insurer issued policy POL-78291048.'],
            ]);
            $p2->issuanceAttempts()->create([
                'attempt_number' => 1,
                'scenario' => 'SUCCESS',
                'status' => 'SUCCESS',
                'request_payload' => ['plan' => 'BASIC', 'customer' => 'Aarav Sharma'],
                'response_payload' => ['policy_number' => 'POL-78291048', 'provider_reference' => 'REF-99210482'],
            ]);
            MockInsurerPolicy::firstOrCreate(
                ['client_request_id' => 'AGT-2026-0002'],
                ['provider_reference' => 'REF-99210482', 'policy_number' => 'POL-78291048', 'status' => 'ISSUED']
            );
        }

        // Sample 3: Timed out policy waiting for reconciliation (NEEDS_RECONCILIATION)
        $pricing3 = $calculator->calculate('PREMIUM', '2026-12-01', '2026-12-07', '1965-02-20', 'INTERNATIONAL', 500000);
        $p3 = PolicyRequest::firstOrCreate(
            ['agent_id' => 'AGENT-09', 'client_request_id' => 'AGT-2026-0003'],
            [
                'customer_name' => 'Priya Patel',
                'date_of_birth' => '1965-02-20',
                'destination' => 'INTERNATIONAL',
                'trip_start' => '2026-12-01',
                'trip_end' => '2026-12-07',
                'plan_code' => 'PREMIUM',
                'sum_insured' => 500000,
                'travel_days' => $pricing3['travel_days'],
                'premium' => $pricing3['premium'],
                'status' => PolicyRequest::STATE_NEEDS_RECONCILIATION,
                'provider_reference' => 'REF-TIMEOUT-773',
                'attempt_count' => 1,
                'last_error' => 'Connection timed out while waiting for insurer response.',
            ]
        );
        if ($p3->wasRecentlyCreated) {
            $p3->stateHistories()->createMany([
                ['from_state' => PolicyRequest::STATE_RECEIVED, 'to_state' => PolicyRequest::STATE_VALIDATED, 'notes' => 'Policy validated.'],
                ['from_state' => PolicyRequest::STATE_VALIDATED, 'to_state' => PolicyRequest::STATE_SUBMITTED, 'notes' => 'Submitted to insurer.'],
                ['from_state' => PolicyRequest::STATE_SUBMITTED, 'to_state' => PolicyRequest::STATE_NEEDS_RECONCILIATION, 'notes' => 'Call timed out; pending reconciliation.'],
            ]);
            // The insurer did issue it internally, so reconciliation will find it!
            MockInsurerPolicy::firstOrCreate(
                ['client_request_id' => 'AGT-2026-0003'],
                ['provider_reference' => 'REF-TIMEOUT-773', 'policy_number' => 'POL-RECON-55829', 'status' => 'ISSUED']
            );
        }

        // Sample 4: Temporary Failure (FAILED - Ready to retry)
        $pricing4 = $calculator->calculate('PLUS', '2026-09-10', '2026-09-12', '1985-08-11', 'DOMESTIC', 300000);
        $p4 = PolicyRequest::firstOrCreate(
            ['agent_id' => 'AGENT-12', 'client_request_id' => 'AGT-2026-0004'],
            [
                'customer_name' => 'Rohan Sen',
                'date_of_birth' => '1985-08-11',
                'destination' => 'DOMESTIC',
                'trip_start' => '2026-09-10',
                'trip_end' => '2026-09-12',
                'plan_code' => 'PLUS',
                'sum_insured' => 300000,
                'travel_days' => $pricing4['travel_days'],
                'premium' => $pricing4['premium'],
                'status' => PolicyRequest::STATE_FAILED,
                'attempt_count' => 1,
                'last_error' => 'Provider timeout / Service Unavailable. Retriable.',
            ]
        );
        if ($p4->wasRecentlyCreated) {
            $p4->stateHistories()->createMany([
                ['from_state' => PolicyRequest::STATE_RECEIVED, 'to_state' => PolicyRequest::STATE_VALIDATED, 'notes' => 'Policy validated.'],
                ['from_state' => PolicyRequest::STATE_VALIDATED, 'to_state' => PolicyRequest::STATE_SUBMITTED, 'notes' => 'Submitted to insurer.'],
                ['from_state' => PolicyRequest::STATE_SUBMITTED, 'to_state' => PolicyRequest::STATE_FAILED, 'notes' => 'Insurer temporary failure: HTTP 503.'],
            ]);
        }
    }
}
