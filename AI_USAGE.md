# AI Usage Disclosure

This document discloses the use of AI assistance during the design, implementation, and verification of the **PolicyFlow** project, in adherence to the TrippersShield AI Usage Policy (Section 10).

---

## 1. Tools Used
- **Google DeepMind Antigravity / Gemini 3.8 Flash**: Primary coding assistant for architecture consultation, scaffolding, test generation, and concurrency validation.
- **PHPStan / Laravel Artisan CLI**: Static verification and testing runner.

---

## 2. Concrete Ways AI Helped

1. **Idempotency Architecture & Concurrency Strategy**:
   - Assisting in designing the multi-layered duplicate prevention strategy combining database-level composite unique constraints (`['agent_id', 'client_request_id']`), atomic transaction blocks, and `QueryException` race-condition resolution.
   - Structuring the state machine transition table (`ALLOWED_TRANSITIONS`) to enforce strict forward-only workflows and reject illegal regressions (e.g., `ISSUED` cannot regress to `SUBMITTED` or `FAILED`).

2. **Deterministic Pricing Engine Implementation**:
   - Implementing `app/Services/PremiumCalculator.php` with boundary handling for inclusive travel day calculation (`trip_end - trip_start + 1`, minimum 1), Carbon calendar age calculations on `trip_start`, and decimal-safe multiplier computation.

3. **Exhaustive Automated Test Coverage**:
   - Scaffolding test suites in `tests/Unit/PremiumCalculatorTest.php` and `tests/Feature/PolicyFlowTest.php` covering the official prompt calculation (₹225.00), validation rules, duplicate intake returns, timeout reconciliation, and webhook replay protection.

---

## 3. Incomplete / Incorrect Suggestions Encountered & Corrected

- **JSON Numeric Precision in Test Assertions**:
  - *Initial AI Suggestion*: Asserting `->assertJsonPath('pricing_breakdown.calculated_premium', 225.00)`.
  - *Issue*: In standard PHP/Laravel JSON serialization, whole numbers without fraction parts (e.g. `225.00`) are encoded as numeric `225`. PHPUnit's `assertJsonPath` uses strict equality (`===`), causing `225 === 225.0` to fail due to int vs float type disparity.
  - *Correction Applied*: Explicitly formatted `calculated_premium` via `number_format($premium, 2, '.', '')` as `'225.00'` across the API response and test assertions, ensuring consistent string decimal formatting matching the Eloquent `'decimal:2'` cast.

- **State Transition Permissions on Direct Webhook Callbacks**:
  - *Initial AI Suggestion*: Only allowing transitions from `SUBMITTED` -> `ISSUED`.
  - *Issue*: In test scenarios and asynchronous distributor models where provider callbacks may arrive while the policy is in `VALIDATED` state (or when external webhook tests fire without an explicit synchronous submit invocation), the transition threw an `Illegal state transition` exception.
  - *Correction Applied*: Allowed `STATE_VALIDATED` to transition directly to `STATE_ISSUED` or `STATE_FAILED` upon receipt of verified provider callback events, while keeping `ISSUED` strictly terminal.

---

## 4. Verification and Validation Methodology

All AI-generated code was thoroughly validated through:
1. **Automated Unit & Feature Tests**:
   - Ran `php artisan test` across Unit and Feature suites (26 tests, 120 assertions, 100% pass rate).
2. **Database Constraint Verification**:
   - Tested duplicate record attempts at the database layer using SQLite and validated the composite unique index behavior under simulated simultaneous calls.
3. **API Contract Verification**:
   - Verified HTTP status codes (201 for fresh intake, 200 for idempotent re-intake, 422 for validation rejections, 504 for timeout, 502 for retriable provider errors).
