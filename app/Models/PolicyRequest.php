<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PolicyRequest extends Model
{
    use HasFactory;

    public const STATE_RECEIVED = 'RECEIVED';
    public const STATE_VALIDATED = 'VALIDATED';
    public const STATE_SUBMITTED = 'SUBMITTED';
    public const STATE_PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    public const STATE_ISSUED = 'ISSUED';
    public const STATE_FAILED = 'FAILED';
    public const STATE_NEEDS_RECONCILIATION = 'NEEDS_RECONCILIATION';

    public const VALID_STATES = [
        self::STATE_RECEIVED,
        self::STATE_VALIDATED,
        self::STATE_SUBMITTED,
        self::STATE_PENDING_CONFIRMATION,
        self::STATE_ISSUED,
        self::STATE_FAILED,
        self::STATE_NEEDS_RECONCILIATION,
    ];

    /**
     * Map of permitted transitions: from_state => [allowed to_states]
     */
    public const ALLOWED_TRANSITIONS = [
        self::STATE_RECEIVED => [
            self::STATE_VALIDATED,
            self::STATE_FAILED,
        ],
        self::STATE_VALIDATED => [
            self::STATE_SUBMITTED,
            self::STATE_FAILED,
        ],
        self::STATE_SUBMITTED => [
            self::STATE_ISSUED,
            self::STATE_PENDING_CONFIRMATION,
            self::STATE_NEEDS_RECONCILIATION,
            self::STATE_FAILED,
        ],
        self::STATE_PENDING_CONFIRMATION => [
            self::STATE_ISSUED,
            self::STATE_FAILED,
            self::STATE_NEEDS_RECONCILIATION,
        ],
        self::STATE_NEEDS_RECONCILIATION => [
            self::STATE_ISSUED,
            self::STATE_FAILED,
            self::STATE_SUBMITTED,
        ],
        self::STATE_FAILED => [
            self::STATE_SUBMITTED, // Retry allowed after failure
        ],
        self::STATE_ISSUED => [], // Terminal state: NO regressions permitted
    ];

    protected $fillable = [
        'client_request_id',
        'agent_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'plan_code',
        'sum_insured',
        'travel_days',
        'premium',
        'status',
        'policy_number',
        'provider_reference',
        'attempt_count',
        'last_error',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'trip_start' => 'date:Y-m-d',
        'trip_end' => 'date:Y-m-d',
        'sum_insured' => 'decimal:2',
        'premium' => 'decimal:2',
        'travel_days' => 'integer',
        'attempt_count' => 'integer',
    ];

    public function stateHistories(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->orderBy('created_at', 'asc');
    }

    public function issuanceAttempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class)->orderBy('attempt_number', 'asc');
    }

    /**
     * Atomically transition state and record state history.
     * Prevents invalid state regressions (e.g., ISSUED -> SUBMITTED).
     *
     * @throws InvalidArgumentException
     */
    public function transitionTo(string $newState, ?string $notes = null): void
    {
        if (!in_array($newState, self::VALID_STATES, true)) {
            throw new InvalidArgumentException("Unknown policy state: '{$newState}'.");
        }

        $currentState = $this->status;

        // Idempotent transition to the same state
        if ($currentState === $newState) {
            return;
        }

        $allowedTransitions = self::ALLOWED_TRANSITIONS[$currentState] ?? [];
        if (!in_array($newState, $allowedTransitions, true)) {
            throw new InvalidArgumentException("Illegal state transition from '{$currentState}' to '{$newState}'.");
        }

        DB::transaction(function () use ($currentState, $newState, $notes) {
            $this->status = $newState;
            $this->save();

            $this->stateHistories()->create([
                'from_state' => $currentState,
                'to_state' => $newState,
                'notes' => $notes,
            ]);
        });
    }
}
