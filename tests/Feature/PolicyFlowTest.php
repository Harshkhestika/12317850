<?php

namespace Tests\Feature;

use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Models\PolicyStateHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PolicyFlowTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'client_request_id' => 'AGT-2026-0001',
        'agent_id' => 'AGENT-17',
        'customer_name' => 'Sample Customer',
        'date_of_birth' => '1998-04-10',
        'destination' => 'INTERNATIONAL',
        'trip_start' => '2026-11-10',
        'trip_end' => '2026-11-14',
        'plan_code' => 'PLUS',
        'sum_insured' => 300000,
    ];

    /**
     * Requirement 1: Valid request produces expected premium snapshot and 201 Created.
     * Specification example:
     * travel_days = 5, age multiplier = 1.00, and premium = 20 * 5 * 1.00 * 1.80 * 1.25 = 225.00
     */
    public function test_valid_policy_intake_creates_record_and_calculates_correct_premium(): void
    {
        $response = $this->postJson('/api/policy-requests', $this->validPayload);

        $response->assertStatus(201)
            ->assertJsonPath('is_duplicate', false)
            ->assertJsonPath('data.status', 'VALIDATED')
            ->assertJsonPath('data.premium', '225.00')
            ->assertJsonPath('data.travel_days', 5)
            ->assertJsonPath('pricing_breakdown.calculated_premium', '225.00');

        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'status' => 'VALIDATED',
            'premium' => 225.00,
        ]);

        $this->assertDatabaseHas('policy_state_histories', [
            'from_state' => 'RECEIVED',
            'to_state' => 'VALIDATED',
        ]);
    }

    /**
     * Requirement 2: Validation rejection on invalid date logic (trip_end < trip_start)
     */
    public function test_intake_rejects_invalid_date_logic(): void
    {
        $invalidPayload = array_merge($this->validPayload, [
            'trip_start' => '2026-11-15',
            'trip_end' => '2026-11-10', // End is before start
        ]);

        $response = $this->postJson('/api/policy-requests', $invalidPayload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['trip_end']);
    }

    /**
     * Requirement 3: Validation rejection on unsupported plan or coverage
     */
    public function test_intake_rejects_unsupported_plan_and_coverage(): void
    {
        $invalidPayload = array_merge($this->validPayload, [
            'plan_code' => 'PLATINUM_VIP',
            'sum_insured' => 999999,
        ]);

        $response = $this->postJson('/api/policy-requests', $invalidPayload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_code', 'sum_insured']);
    }

    /**
     * Reliability Case 1: Duplicate intake prevention
     * Submitting the exact same agent_id + client_request_id returns 200 OK and does not duplicate rows.
     */
    public function test_duplicate_intake_returns_200_and_does_not_create_duplicate_row(): void
    {
        // First submission creates request
        $firstResponse = $this->postJson('/api/policy-requests', $this->validPayload);
        $firstResponse->assertStatus(201);
        $policyId = $firstResponse->json('data.id');

        $this->assertEquals(1, PolicyRequest::count());

        // Repeated submission returns 200 OK with identical policy ID
        $secondResponse = $this->postJson('/api/policy-requests', $this->validPayload);
        $secondResponse->assertStatus(200)
            ->assertJsonPath('is_duplicate', true)
            ->assertJsonPath('data.id', $policyId);

        // Verify count in database is still exactly 1
        $this->assertEquals(1, PolicyRequest::count());
    }

    /**
     * Requirement 4: Issuance submission succeeds and produces unique policy number
     */
    public function test_submit_issuance_transitions_to_issued(): void
    {
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id');

        $submitRes = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $submitRes->assertStatus(200)
            ->assertJsonPath('status', 'ISSUED')
            ->assertJsonStructure(['policy_number', 'provider_reference']);

        $policy = PolicyRequest::find($policyId);
        $this->assertEquals('ISSUED', $policy->status);
        $this->assertNotNull($policy->policy_number);
        $this->assertNotNull($policy->provider_reference);

        // Verify state audit trail contains transition
        $this->assertDatabaseHas('policy_state_histories', [
            'policy_request_id' => $policyId,
            'to_state' => 'ISSUED',
        ]);
    }

    /**
     * Repeated submission of an already ISSUED policy returns 200 OK without re-issuing
     */
    public function test_submit_on_already_issued_policy_is_safely_ignored(): void
    {
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id');

        // Submit once -> ISSUED
        $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS']);
        $policyNumber = PolicyRequest::find($policyId)->policy_number;

        // Submit second time -> Idempotent 200 OK
        $res = $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS']);
        $res->assertStatus(200)
            ->assertJsonPath('status', 'ISSUED')
            ->assertJsonPath('policy_number', $policyNumber);

        // Verify attempts did not re-trigger
        $this->assertEquals(1, PolicyRequest::find($policyId)->attempt_count);
    }

    /**
     * Reliability Case 2: Duplicate Callback Idempotency
     * A duplicate webhook SUCCESS callback does not override or duplicate an already 'ISSUED' policy.
     */
    public function test_duplicate_webhook_callback_does_not_override_issued_policy(): void
    {
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id');

        // Manually trigger initial SUCCESS callback
        $firstCallback = $this->postJson('/api/mock-insurer/callback', [
            'client_request_id' => 'AGT-2026-0001',
            'event' => 'SUCCESS',
            'policy_number' => 'POL-INITIAL-999',
            'provider_reference' => 'REF-ORIGINAL-888',
        ]);

        $firstCallback->assertStatus(200)
            ->assertJsonPath('status', 'ISSUED')
            ->assertJsonPath('policy_number', 'POL-INITIAL-999');

        $policy = PolicyRequest::find($policyId);
        $this->assertEquals('ISSUED', $policy->status);
        $this->assertEquals('POL-INITIAL-999', $policy->policy_number);
        $historiesCount = PolicyStateHistory::where('policy_request_id', $policyId)->count();

        // Send duplicate callback (even with differing hypothetical policy_number)
        $duplicateCallback = $this->postJson('/api/mock-insurer/callback', [
            'client_request_id' => 'AGT-2026-0001',
            'event' => 'SUCCESS',
            'policy_number' => 'POL-NEW-OVERWRITE-ATTEMPT',
            'provider_reference' => 'REF-NEW-888',
        ]);

        $duplicateCallback->assertStatus(200)
            ->assertJsonPath('is_idempotent_noop', true)
            ->assertJsonPath('policy_number', 'POL-INITIAL-999');

        // Confirm policy number was preserved and no new state history was added
        $policy->refresh();
        $this->assertEquals('POL-INITIAL-999', $policy->policy_number);
        $this->assertEquals($historiesCount, PolicyStateHistory::where('policy_request_id', $policyId)->count());
    }

    /**
     * Reliability Case 3: Timeout after success & safe reconciliation
     * Insurer issues policy internally but HTTP call times out.
     * System marks NEEDS_RECONCILIATION and resolves safely via /reconcile endpoint.
     */
    public function test_timeout_after_success_and_reconciliation_flow(): void
    {
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id');

        // Submit with TIMEOUT_AFTER_ISSUE scenario
        $submitRes = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
        ]);

        $submitRes->assertStatus(504)
            ->assertJsonPath('status', 'NEEDS_RECONCILIATION');

        $policy = PolicyRequest::find($policyId);
        $this->assertEquals('NEEDS_RECONCILIATION', $policy->status);

        // Run reconciliation endpoint
        $reconcileRes = $this->postJson("/api/policy-requests/{$policyId}/reconcile");

        $reconcileRes->assertStatus(200)
            ->assertJsonPath('status', 'ISSUED');

        $policy->refresh();
        $this->assertEquals('ISSUED', $policy->status);
        $this->assertNotNull($policy->policy_number);
        $this->assertDatabaseHas('policy_state_histories', [
            'policy_request_id' => $policyId,
            'to_state' => 'ISSUED',
        ]);
    }

    /**
     * Reliability Case 4: Safe retry after temporary failure
     * A temporary failure transitions to FAILED, subsequent retry succeeds and retains immutable premium.
     */
    public function test_retry_after_temporary_failure_succeeds_with_immutable_premium(): void
    {
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id');

        // Attempt 1: TEMPORARY_FAILURE
        $failRes = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'TEMPORARY_FAILURE',
        ]);

        $failRes->assertStatus(502)
            ->assertJsonPath('status', 'FAILED');

        $policy = PolicyRequest::find($policyId);
        $this->assertEquals('FAILED', $policy->status);
        $this->assertEquals('225.00', (string) $policy->premium);

        // Attempt 2: Retry with SUCCESS
        $retryRes = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $retryRes->assertStatus(200)
            ->assertJsonPath('status', 'ISSUED');

        $policy->refresh();
        $this->assertEquals('ISSUED', $policy->status);
        $this->assertEquals(2, $policy->attempt_count);
        $this->assertEquals('225.00', (string) $policy->premium); // Original snapshot retained
    }

    /**
     * State Machine Rule: Reject invalid state regression (e.g. ISSUED -> SUBMITTED)
     */
    public function test_state_machine_rejects_invalid_regressions(): void
    {
        $policy = PolicyRequest::create(array_merge($this->validPayload, [
            'travel_days' => 5,
            'premium' => 225.00,
            'status' => 'ISSUED',
            'policy_number' => 'POL-TEST-001',
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Illegal state transition from 'ISSUED' to 'SUBMITTED'.");

        $policy->transitionTo('SUBMITTED');
    }

    /**
     * Audit Trail: Read endpoint GET /api/policy-requests/{id} returns details and history
     */
    public function test_read_endpoint_returns_audit_trail_and_attempts(): void
    {
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id');

        $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS']);

        $showRes = $this->getJson("/api/policy-requests/{$policyId}");

        $showRes->assertStatus(200)
            ->assertJsonPath('data.status', 'ISSUED')
            ->assertJsonPath('data.premium', '225.00')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'client_request_id',
                    'agent_id',
                    'customer_name',
                    'status',
                    'premium',
                    'policy_number',
                    'provider_reference',
                    'state_histories',
                    'issuance_attempts',
                ],
            ]);
    }

    /**
     * List endpoint: GET /api/policy-requests with status filter & pagination
     */
    public function test_list_endpoint_filters_by_status(): void
    {
        // Create 2 records
        $this->postJson('/api/policy-requests', $this->validPayload);
        $this->postJson('/api/policy-requests', array_merge($this->validPayload, [
            'client_request_id' => 'AGT-2026-0002',
        ]));

        // Check list
        $res = $this->getJson('/api/policy-requests?status=VALIDATED');
        $res->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Webhook callback with TEMPORARY_FAILURE transitions policy to FAILED
     */
    public function test_webhook_temporary_failure_callback(): void
    {
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id');

        $callbackRes = $this->postJson('/api/mock-insurer/callback', [
            'client_request_id' => 'AGT-2026-0001',
            'event' => 'TEMPORARY_FAILURE',
        ]);

        $callbackRes->assertStatus(200)
            ->assertJsonPath('status', 'FAILED');

        $this->assertEquals('FAILED', PolicyRequest::find($policyId)->status);
    }

    /**
     * Calling reconcile on a policy not in NEEDS_RECONCILIATION or PENDING_CONFIRMATION returns 400
     */
    public function test_reconcile_on_invalid_status_returns_400(): void
    {
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id'); // status is VALIDATED

        $res = $this->postJson("/api/policy-requests/{$policyId}/reconcile");
        $res->assertStatus(400)
            ->assertJsonPath('status', 'VALIDATED');
    }

    /**
     * Mock insurer provider status lookup endpoint returns 200 for existing and 404 for unknown
     */
    public function test_mock_insurer_status_lookup(): void
    {
        // 404 for unknown
        $this->getJson('/api/mock-insurer/status/UNKNOWN-REQ-999')->assertStatus(404);

        // Submit a policy to populate mock insurer store
        $createRes = $this->postJson('/api/policy-requests', $this->validPayload);
        $policyId = $createRes->json('data.id');
        $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS']);

        // 200 for known client_request_id
        $this->getJson('/api/mock-insurer/status/AGT-2026-0001')
            ->assertStatus(200)
            ->assertJsonPath('status', 'ISSUED')
            ->assertJsonPath('client_request_id', 'AGT-2026-0001');
    }
}
