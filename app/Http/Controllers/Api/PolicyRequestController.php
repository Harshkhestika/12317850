<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePolicyRequest;
use App\Models\PolicyRequest;
use App\Services\InsurerService;
use App\Services\PremiumCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PolicyRequestController extends Controller
{
    /**
     * Intake endpoint: POST /api/policy-requests
     * Create a new policy request or safely return existing request (idempotent).
     */
    public function store(StorePolicyRequest $request, PremiumCalculator $calculator): JsonResponse
    {
        $validated = $request->validated();
        $agentId = $validated['agent_id'];
        $clientRequestId = $validated['client_request_id'];

        // Step 1: Idempotency check - if already exists, return existing immediately with 200 OK
        $existing = PolicyRequest::where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Policy request already exists (idempotent response).',
                'is_duplicate' => true,
                'data' => $existing,
            ], 200);
        }

        // Step 2: Calculate premium using deterministic business rules
        $pricing = $calculator->calculate(
            planCode: $validated['plan_code'],
            tripStart: $validated['trip_start'],
            tripEnd: $validated['trip_end'],
            dateOfBirth: $validated['date_of_birth'],
            destination: $validated['destination'],
            sumInsured: $validated['sum_insured']
        );

        // Step 3: Concurrency-safe atomic insert with duplicate key handling
        try {
            $policy = DB::transaction(function () use ($validated, $pricing) {
                $policy = PolicyRequest::create([
                    'client_request_id' => $validated['client_request_id'],
                    'agent_id' => $validated['agent_id'],
                    'customer_name' => $validated['customer_name'],
                    'date_of_birth' => $validated['date_of_birth'],
                    'destination' => $validated['destination'],
                    'trip_start' => $validated['trip_start'],
                    'trip_end' => $validated['trip_end'],
                    'plan_code' => $validated['plan_code'],
                    'sum_insured' => $validated['sum_insured'],
                    'travel_days' => $pricing['travel_days'],
                    'premium' => $pricing['premium'],
                    'status' => PolicyRequest::STATE_VALIDATED,
                ]);

                // Record initial state transition in audit trail
                $policy->stateHistories()->create([
                    'from_state' => PolicyRequest::STATE_RECEIVED,
                    'to_state' => PolicyRequest::STATE_VALIDATED,
                    'notes' => "Policy request intake created and pricing validated. Premium: ₹{$pricing['premium']} ({$pricing['travel_days']} travel days).",
                ]);

                return $policy;
            });

            return response()->json([
                'message' => 'Policy request created and validated successfully.',
                'is_duplicate' => false,
                'pricing_breakdown' => [
                    'base_rate' => $pricing['base_rate'],
                    'travel_days' => $pricing['travel_days'],
                    'age_on_trip_start' => $pricing['age_on_trip_start'],
                    'age_multiplier' => $pricing['age_multiplier'],
                    'destination_multiplier' => $pricing['destination_multiplier'],
                    'coverage_multiplier' => $pricing['coverage_multiplier'],
                    'calculated_premium' => $pricing['premium'],
                ],
                'data' => $policy,
            ], 201);
        } catch (QueryException $e) {
            // In case of near-simultaneous concurrent requests, catch unique constraint violation
            $duplicate = PolicyRequest::where('agent_id', $agentId)
                ->where('client_request_id', $clientRequestId)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'message' => 'Policy request already exists (resolved concurrent intake).',
                    'is_duplicate' => true,
                    'data' => $duplicate,
                ], 200);
            }

            throw $e;
        }
    }

    /**
     * Read endpoint: GET /api/policy-requests/{id}
     * Retrieve status, premium, references, and complete audit trail.
     */
    public function show(int $id): JsonResponse
    {
        $policy = PolicyRequest::with(['stateHistories', 'issuanceAttempts'])->find($id);

        if (!$policy) {
            return response()->json([
                'message' => "Policy request with ID {$id} not found.",
            ], 404);
        }

        return response()->json([
            'data' => $policy,
        ], 200);
    }

    /**
     * List endpoint: GET /api/policy-requests
     * List requests with status filter and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PolicyRequest::query()->latest();

        if ($request->has('status') && $request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('agent_id') && $request->filled('agent_id')) {
            $query->where('agent_id', $request->query('agent_id'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $policies = $query->paginate($perPage);

        return response()->json($policies, 200);
    }

    /**
     * Submit endpoint: POST /api/policy-requests/{id}/submit
     * Start or safely resume issuance with the insurer.
     */
    public function submit(Request $request, int $id, InsurerService $insurerService): JsonResponse
    {
        $policy = PolicyRequest::find($id);

        if (!$policy) {
            return response()->json([
                'message' => "Policy request with ID {$id} not found.",
            ], 404);
        }

        // Idempotency: If already ISSUED, return immediately without re-calling the provider
        if ($policy->status === PolicyRequest::STATE_ISSUED) {
            return response()->json([
                'message' => 'Policy has already been issued. Duplicate submission ignored safely.',
                'status' => $policy->status,
                'policy_number' => $policy->policy_number,
                'provider_reference' => $policy->provider_reference,
                'data' => $policy,
            ], 200);
        }

        // If PENDING_CONFIRMATION, avoid blind reissue
        if ($policy->status === PolicyRequest::STATE_PENDING_CONFIRMATION) {
            return response()->json([
                'message' => 'Policy is awaiting confirmation from provider callback. Reissue blocked.',
                'status' => $policy->status,
                'provider_reference' => $policy->provider_reference,
                'data' => $policy,
            ], 200);
        }

        // Scenario header or input for deterministic testing/review
        $scenario = $request->header('X-Mock-Scenario', $request->input('scenario', 'SUCCESS'));

        // Transition to SUBMITTED
        $policy->transitionTo(PolicyRequest::STATE_SUBMITTED, "Issuance attempt initiated (Scenario: {$scenario}).");
        $policy->increment('attempt_count');

        // Submit to insurer service
        $result = $insurerService->submitPolicy($policy, $scenario);

        if ($result['status'] === 'ISSUED') {
            DB::transaction(function () use ($policy, $result) {
                $policy->policy_number = $result['policy_number'];
                $policy->provider_reference = $result['provider_reference'];
                $policy->last_error = null;
                $policy->save();

                $policy->transitionTo(PolicyRequest::STATE_ISSUED, "Insurer successfully issued policy number: {$result['policy_number']}");
            });

            return response()->json([
                'message' => 'Policy successfully issued by insurer.',
                'status' => PolicyRequest::STATE_ISSUED,
                'policy_number' => $policy->policy_number,
                'provider_reference' => $policy->provider_reference,
                'data' => $policy->fresh(['stateHistories', 'issuanceAttempts']),
            ], 200);
        }

        if (!empty($result['needs_reconciliation'])) {
            DB::transaction(function () use ($policy, $result) {
                if (isset($result['provider_reference'])) {
                    $policy->provider_reference = $result['provider_reference'];
                }
                $policy->last_error = $result['error'];
                $policy->save();

                $policy->transitionTo(
                    PolicyRequest::STATE_NEEDS_RECONCILIATION,
                    "Insurer timed out. State marked as NEEDS_RECONCILIATION to prevent duplicate issuance."
                );
            });

            return response()->json([
                'message' => 'Insurer connection timed out. Marked for reconciliation.',
                'status' => PolicyRequest::STATE_NEEDS_RECONCILIATION,
                'provider_reference' => $policy->provider_reference,
                'error' => $result['error'],
                'data' => $policy->fresh(['stateHistories', 'issuanceAttempts']),
            ], 504);
        }

        if ($result['status'] === 'PENDING_CONFIRMATION') {
            DB::transaction(function () use ($policy, $result) {
                $policy->provider_reference = $result['provider_reference'];
                $policy->save();

                $policy->transitionTo(
                    PolicyRequest::STATE_PENDING_CONFIRMATION,
                    "Provider acknowledged request. Waiting for delayed webhook callback."
                );
            });

            return response()->json([
                'message' => 'Policy submission accepted. Waiting for provider confirmation callback.',
                'status' => PolicyRequest::STATE_PENDING_CONFIRMATION,
                'provider_reference' => $policy->provider_reference,
                'data' => $policy->fresh(['stateHistories', 'issuanceAttempts']),
            ], 202);
        }

        // Temporary or unhandled failure
        DB::transaction(function () use ($policy, $result) {
            $policy->last_error = $result['error'] ?? 'Provider error';
            $policy->save();

            $policy->transitionTo(
                PolicyRequest::STATE_FAILED,
                "Insurer issuance failed: " . ($result['error'] ?? 'Unknown error')
            );
        });

        return response()->json([
            'message' => 'Policy issuance failed at provider. Can be retried.',
            'status' => PolicyRequest::STATE_FAILED,
            'error' => $policy->last_error,
            'data' => $policy->fresh(['stateHistories', 'issuanceAttempts']),
        ], 502);
    }

    /**
     * Reconciliation endpoint: POST /api/policy-requests/{id}/reconcile
     * Resolve policies in NEEDS_RECONCILIATION or PENDING_CONFIRMATION by querying provider status.
     */
    public function reconcile(int $id, InsurerService $insurerService): JsonResponse
    {
        $policy = PolicyRequest::find($id);

        if (!$policy) {
            return response()->json(['message' => "Policy request with ID {$id} not found."], 404);
        }

        if (!in_array($policy->status, [PolicyRequest::STATE_NEEDS_RECONCILIATION, PolicyRequest::STATE_PENDING_CONFIRMATION], true)) {
            return response()->json([
                'message' => "Policy status is '{$policy->status}'. Reconciliation only applies to NEEDS_RECONCILIATION or PENDING_CONFIRMATION.",
                'status' => $policy->status,
            ], 400);
        }

        $providerPolicy = $insurerService->queryProviderStatus($policy->client_request_id);

        if ($providerPolicy && $providerPolicy->status === 'ISSUED') {
            DB::transaction(function () use ($policy, $providerPolicy) {
                $policy->policy_number = $providerPolicy->policy_number;
                $policy->provider_reference = $providerPolicy->provider_reference;
                $policy->last_error = null;
                $policy->save();

                $policy->transitionTo(
                    PolicyRequest::STATE_ISSUED,
                    "Reconciliation successful: Provider confirmed policy was issued with policy number: {$providerPolicy->policy_number}"
                );
            });

            return response()->json([
                'message' => 'Policy reconciled successfully and marked as ISSUED.',
                'status' => PolicyRequest::STATE_ISSUED,
                'policy_number' => $policy->policy_number,
                'provider_reference' => $policy->provider_reference,
                'data' => $policy->fresh(['stateHistories', 'issuanceAttempts']),
            ], 200);
        }

        // Provider has no record or failed
        $policy->transitionTo(
            PolicyRequest::STATE_FAILED,
            "Reconciliation determined provider did not issue policy. Marked as FAILED for retry."
        );

        return response()->json([
            'message' => 'Reconciliation completed. No issued policy found at provider; marked as FAILED.',
            'status' => PolicyRequest::STATE_FAILED,
            'data' => $policy->fresh(['stateHistories', 'issuanceAttempts']),
        ], 200);
    }
}
