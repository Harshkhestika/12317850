<?php

namespace App\Services;

use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use Exception;
use Illuminate\Support\Str;

class InsurerService
{
    /**
     * Submit a policy request to the insurer.
     *
     * @param PolicyRequest $policy
     * @param string|null $scenario Override scenario: SUCCESS, TEMPORARY_FAILURE, TIMEOUT_AFTER_ISSUE, DELAYED_CALLBACK
     * @return array
     */
    public function submitPolicy(PolicyRequest $policy, ?string $scenario = null): array
    {
        $scenario = strtoupper(trim($scenario ?? 'SUCCESS'));
        $attemptNumber = $policy->attempt_count + 1;

        // Record attempt start
        $requestPayload = [
            'client_request_id' => $policy->client_request_id,
            'agent_id' => $policy->agent_id,
            'customer_name' => $policy->customer_name,
            'plan_code' => $policy->plan_code,
            'sum_insured' => $policy->sum_insured,
            'premium' => $policy->premium,
            'scenario' => $scenario,
        ];

        try {
            switch ($scenario) {
                case 'TEMPORARY_FAILURE':
                    $response = [
                        'success' => false,
                        'status' => 'TEMPORARY_FAILURE',
                        'error' => 'Provider timeout / Service Unavailable. Retriable.',
                        'retriable' => true,
                    ];
                    $policy->issuanceAttempts()->create([
                        'attempt_number' => $attemptNumber,
                        'scenario' => $scenario,
                        'request_payload' => $requestPayload,
                        'response_payload' => $response,
                        'status' => 'FAILED',
                    ]);
                    return $response;

                case 'TIMEOUT_AFTER_ISSUE':
                    // Insurer issued the policy internally, but client network connection timed out!
                    $providerRef = 'REF-' . strtoupper(Str::random(10));
                    $policyNum = 'POL-' . strtoupper(Str::random(10));

                    MockInsurerPolicy::updateOrCreate(
                        ['client_request_id' => $policy->client_request_id],
                        [
                            'provider_reference' => $providerRef,
                            'policy_number' => $policyNum,
                            'status' => 'ISSUED',
                        ]
                    );

                    $response = [
                        'success' => false,
                        'status' => 'TIMEOUT',
                        'error' => 'Connection timed out while waiting for insurer response. Policy status uncertain.',
                        'provider_reference' => $providerRef,
                        'needs_reconciliation' => true,
                    ];

                    $policy->issuanceAttempts()->create([
                        'attempt_number' => $attemptNumber,
                        'scenario' => $scenario,
                        'request_payload' => $requestPayload,
                        'response_payload' => $response,
                        'status' => 'TIMEOUT',
                    ]);

                    return $response;

                case 'DELAYED_CALLBACK':
                    $providerRef = 'REF-' . strtoupper(Str::random(10));

                    MockInsurerPolicy::updateOrCreate(
                        ['client_request_id' => $policy->client_request_id],
                        [
                            'provider_reference' => $providerRef,
                            'policy_number' => 'POL-PENDING-' . strtoupper(Str::random(6)),
                            'status' => 'PENDING',
                        ]
                    );

                    $response = [
                        'success' => true,
                        'status' => 'PENDING_CONFIRMATION',
                        'provider_reference' => $providerRef,
                        'message' => 'Policy submitted. Awaiting asynchronous provider callback.',
                    ];

                    $policy->issuanceAttempts()->create([
                        'attempt_number' => $attemptNumber,
                        'scenario' => $scenario,
                        'request_payload' => $requestPayload,
                        'response_payload' => $response,
                        'status' => 'PENDING',
                    ]);

                    return $response;

                case 'SUCCESS':
                default:
                    // Standard success flow: Insurer issues policy and returns references immediately
                    $providerRef = 'REF-' . strtoupper(Str::random(10));
                    $policyNum = 'POL-' . strtoupper(Str::random(10));

                    MockInsurerPolicy::updateOrCreate(
                        ['client_request_id' => $policy->client_request_id],
                        [
                            'provider_reference' => $providerRef,
                            'policy_number' => $policyNum,
                            'status' => 'ISSUED',
                        ]
                    );

                    $response = [
                        'success' => true,
                        'status' => 'ISSUED',
                        'provider_reference' => $providerRef,
                        'policy_number' => $policyNum,
                    ];

                    $policy->issuanceAttempts()->create([
                        'attempt_number' => $attemptNumber,
                        'scenario' => $scenario,
                        'request_payload' => $requestPayload,
                        'response_payload' => $response,
                        'status' => 'SUCCESS',
                    ]);

                    return $response;
            }
        } catch (Exception $e) {
            $errResponse = [
                'success' => false,
                'status' => 'EXCEPTION',
                'error' => $e->getMessage(),
                'retriable' => true,
            ];

            $policy->issuanceAttempts()->create([
                'attempt_number' => $attemptNumber,
                'scenario' => $scenario,
                'request_payload' => $requestPayload,
                'response_payload' => $errResponse,
                'status' => 'ERROR',
            ]);

            return $errResponse;
        }
    }

    /**
     * Query mock provider status for reconciliation.
     */
    public function queryProviderStatus(string $clientRequestId): ?MockInsurerPolicy
    {
        return MockInsurerPolicy::where('client_request_id', $clientRequestId)->first();
    }
}
