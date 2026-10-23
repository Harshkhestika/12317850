<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MockInsurerController extends Controller
{
    /**
     * Webhook callback receiver: POST /api/mock-insurer/callback
     * Processes simulated provider events with strict idempotency.
     */
    public function callback(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_request_id' => ['required', 'string'],
            'event' => ['required', 'string', 'in:SUCCESS,TEMPORARY_FAILURE'],
            'policy_number' => ['nullable', 'string'],
            'provider_reference' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid callback payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $clientRequestId = $request->input('client_request_id');
        $event = $request->input('event');

        $policy = PolicyRequest::where('client_request_id', $clientRequestId)->first();

        if (!$policy) {
            return response()->json([
                'message' => "Policy with client_request_id '{$clientRequestId}' not found.",
            ], 404);
        }

        // CRITICAL IDEMPOTENCY:
        // If policy is already confirmed ISSUED, immediately return 200 without modifying state or duplicates
        if ($policy->status === PolicyRequest::STATE_ISSUED) {
            return response()->json([
                'message' => 'Policy is already in ISSUED state. Duplicate callback ignored safely.',
                'status' => PolicyRequest::STATE_ISSUED,
                'policy_number' => $policy->policy_number,
                'provider_reference' => $policy->provider_reference,
                'is_idempotent_noop' => true,
            ], 200);
        }

        if ($event === 'SUCCESS') {
            $policyNumber = $request->input('policy_number') ?? ('POL-' . strtoupper(Str::random(10)));
            $providerReference = $request->input('provider_reference') ?? ($policy->provider_reference ?? ('REF-' . strtoupper(Str::random(10))));

            DB::transaction(function () use ($policy, $policyNumber, $providerReference) {
                $policy->policy_number = $policyNumber;
                $policy->provider_reference = $providerReference;
                $policy->last_error = null;
                $policy->save();

                $policy->transitionTo(
                    PolicyRequest::STATE_ISSUED,
                    "Policy confirmed and issued via asynchronous insurer callback. Policy number: {$policyNumber}"
                );
            });

            return response()->json([
                'message' => 'Callback processed: Policy status transitioned to ISSUED.',
                'status' => PolicyRequest::STATE_ISSUED,
                'policy_number' => $policy->policy_number,
                'provider_reference' => $policy->provider_reference,
            ], 200);
        }

        // Event is TEMPORARY_FAILURE
        DB::transaction(function () use ($policy) {
            $policy->last_error = 'Provider asynchronous failure callback received.';
            $policy->save();

            $policy->transitionTo(
                PolicyRequest::STATE_FAILED,
                'Provider callback reported temporary failure during issuance.'
            );
        });

        return response()->json([
            'message' => 'Callback processed: Policy status transitioned to FAILED.',
            'status' => PolicyRequest::STATE_FAILED,
        ], 200);
    }

    /**
     * Provider status lookup: GET /api/mock-insurer/status/{client_request_id}
     * Used by reconciliation logic to query external insurer state.
     */
    public function status(string $clientRequestId): JsonResponse
    {
        $mockPolicy = MockInsurerPolicy::where('client_request_id', $clientRequestId)->first();

        if (!$mockPolicy) {
            return response()->json([
                'status' => 'NOT_FOUND',
                'message' => "No provider record for client_request_id '{$clientRequestId}'.",
            ], 404);
        }

        return response()->json([
            'status' => $mockPolicy->status,
            'client_request_id' => $mockPolicy->client_request_id,
            'provider_reference' => $mockPolicy->provider_reference,
            'policy_number' => $mockPolicy->policy_number,
        ], 200);
    }
}
