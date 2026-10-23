# PolicyFlow (`policyflow-12317850`)
### Fault-Tolerant Insurance Issuance & Reconciliation Engine
**TrippersShield / Campus Engineering Challenge**

---

## 1. Overview & Business Problem

PolicyFlow is a fault-tolerant insurance issuance and reconciliation engine built with **PHP 8.2+** and **Laravel 11**. It safely ingests travel insurance policy requests from distribution agents, calculates deterministic premiums, coordinates issuance with external insurers, and tracks requests through an explicit audit trail.

The external insurer environment is inherently unreliable: requests can time out, network responses may be dropped, and provider callbacks can be duplicated or delayed. PolicyFlow guarantees **strict idempotency**, **duplicate prevention**, and **safe state transitions** under retry and failure conditions.

---

## 2. Architecture & Data Flow

```
+-----------------------------------------------------------------------------------------+
|                                    PolicyFlow Engine                                    |
|                                                                                         |
|   +-------------------+       +-----------------------+       +---------------------+   |
|   |   Agent Intake    | ----> |  Premium Calculator   | ----> |   State Machine     |   |
|   |  POST /api/policy |       |  (Deterministic Math) |       | (Forward-only Audit)|   |
|   |     -requests     |       +-----------------------+       +---------------------+   |
|   +-------------------+                                                  |              |
|             |                                                            v              |
|             | (agent_id, client_request_id)                   +---------------------+   |
|             v                                                 |   Insurer Service   |   |
|   +-------------------+                                       | (Attempts & Retries)|   |
|   | Database Unique   |                                       +---------------------+   |
|   | Composite Index   |                                                  |              |
+---|-------------------|--------------------------------------------------|--------------+
            |                                                              |
            v                                                              v
   [ 200 OK Safe Replay ]                                       [ Mock Insurer / Callback ]
```

### Core Entities & Database Design
1. **`policy_requests`**:
   - Primary record storing policy attributes, dates, customer details, and snapshot premium.
   - **Composite Unique Index**: `UNIQUE ('agent_id', 'client_request_id')` ensures duplicate intake prevention at the database engine level.
   - **Unique Index**: `UNIQUE ('policy_number')` ensures no two policies ever receive identical numbers.
2. **`policy_state_histories`**:
   - Immutable audit trail recording each state change: `policy_request_id`, `from_state`, `to_state`, `notes`, and timestamps.
3. **`issuance_attempts`**:
   - Detailed ledger of every insurer interaction: `attempt_number`, `scenario`, `request_payload`, `response_payload`, and `status`.
4. **`mock_insurer_policies`**:
   - Internal datastore used by the mock insurer to simulate third-party persistence and status queries during reconciliation.

---

## 3. Duplicate Prevention & Idempotency Strategy

PolicyFlow implements a defense-in-depth approach against duplicate issuance:

1. **Database-Level Composite Constraint**:
   - Application-layer checks alone (`where()->first()`) are susceptible to concurrent race conditions. PolicyFlow pairs application checks with a database-enforced unique index on `(agent_id, client_request_id)`.
   - If two identical requests hit the server concurrently, the database constraint raises a `QueryException` on the losing transaction, which the controller catches and safely resolves by returning the existing record with HTTP `200 OK`.
2. **State Machine Immutability (`transitionTo`)**:
   - Once a policy transitions to `ISSUED`, it is terminal. Regressive transitions (e.g. `ISSUED -> SUBMITTED` or `ISSUED -> FAILED`) are strictly rejected.
   - Duplicate submissions to `POST /api/policy-requests/{id}/submit` on an already issued policy immediately return `200 OK` with the existing policy number, avoiding redundant provider calls.
3. **Idempotent Webhook Processing**:
   - When duplicate callbacks arrive (`POST /api/mock-insurer/callback`), the system checks the current status. If the policy is already `ISSUED`, it acknowledges with `200 OK` and performs no database updates or duplicate audit entries (`is_idempotent_noop: true`).

---

## 4. Implemented Reliability Scenarios

| Scenario | Behavior & Fault Handling | API / Test Proof |
| :--- | :--- | :--- |
| **1. Duplicate Intake** | Exact duplicate `(agent_id, client_request_id)` returns HTTP 200 OK with existing record; exactly 1 DB row exists. | `test_duplicate_intake_returns_200_and_does_not_create_duplicate_row` |
| **2. Duplicate Callback** | Re-delivered provider callback for an `ISSUED` policy returns 200 OK without modifying status or corrupting history. | `test_duplicate_webhook_callback_does_not_override_issued_policy` |
| **3. Timeout After Success & Reconciliation** | Insurer issues policy internally but the client call times out (HTTP 504). Policy transitions to `NEEDS_RECONCILIATION`. Calling `POST /api/policy-requests/{id}/reconcile` queries provider status and resolves to `ISSUED`. | `test_timeout_after_success_and_reconciliation_flow` |
| **4. Retry After Temporary Failure** | Insurer returns temporary failure (HTTP 502, `FAILED`). Subsequent retry increments attempt count, reuses the original snapshot premium, and transitions to `ISSUED`. | `test_retry_after_temporary_failure_succeeds_with_immutable_premium` |

---

## 5. Premium Calculation Logic

The premium is calculated using deterministic fictional business rules:
$$\text{premium} = \text{base\_daily\_rate} \times \text{travel\_days} \times \text{age\_multiplier} \times \text{destination\_multiplier} \times \text{coverage\_multiplier}$$

- **Base Daily Rate**: `BASIC = ₹12.00`, `PLUS = ₹20.00`, `PREMIUM = ₹30.00`
- **Travel Days**: Inclusive calculation: $\text{trip\_end} - \text{trip\_start} + 1$ (minimum 1 day).
- **Age Multiplier (at trip start)**:
  - 0–17 years: `1.10`
  - 18–60 years: `1.00`
  - 61–70 years: `1.50`
  - 71+ years: `2.00`
- **Destination Multiplier**: `DOMESTIC = 1.00`, `INTERNATIONAL = 1.80`
- **Coverage Multiplier**: `₹100,000 = 1.00`, `₹300,000 = 1.25`, `₹500,000 = 1.50`

### Specification Example:
- Plan: `PLUS` (₹20), Destination: `INTERNATIONAL` (1.80), Coverage: `₹300,000` (1.25)
- Dates: `2026-11-10` to `2026-11-14` (5 days), DOB: `1998-04-10` (Age 28 -> 1.00)
- $\text{Premium} = 20 \times 5 \times 1.00 \times 1.80 \times 1.25 = \mathbf{₹225.00}$

---

## 6. Local Setup & Running Instructions

### Prerequisites
- PHP 8.2 or newer with extensions: `pdo_sqlite`, `bcmath`, `mbstring`, `curl`
- Composer 2.x

### Step 1: Install Dependencies
```bash
composer install
```

### Step 2: Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### Step 3: Database Migrations
PolicyFlow defaults to SQLite for zero-setup local execution (no external database server required):
```bash
touch database/database.sqlite
php artisan migrate
```
*(Optional: To use MySQL or PostgreSQL, update `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env` and run `php artisan migrate`.)*

### Step 4: Run the Automated Test Suite
```bash
php artisan test
```
*Runs all 26 unit and feature tests validating business logic, reliability scenarios, and idempotency.*

### Step 5: Start the Local Development Server
```bash
php artisan serve
```
Application will be available at: `http://127.0.0.1:8000`

---

## 7. API Reference & Sample cURL Commands

### 1. Intake Request (Create / Idempotent Return)
`POST /api/policy-requests`

```bash
curl -X POST http://127.0.0.1:8000/api/policy-requests \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "agent_id": "AGENT-17",
    "customer_name": "Sample Customer",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }'
```
- **Responses**:
  - `201 Created`: Fresh request created with status `VALIDATED` and calculated premium.
  - `200 OK`: Existing request returned safely (idempotent submission).
  - `422 Unprocessable Content`: Validation failure (e.g. `trip_end < trip_start` or invalid plan).

---

### 2. Submit Request for Issuance
`POST /api/policy-requests/{id}/submit`

Supports optional `scenario` in JSON payload or `X-Mock-Scenario` header (`SUCCESS`, `TEMPORARY_FAILURE`, `TIMEOUT_AFTER_ISSUE`, `DELAYED_CALLBACK`):

```bash
curl -X POST http://127.0.0.1:8000/api/policy-requests/1/submit \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"scenario": "SUCCESS"}'
```
- **Responses**:
  - `200 OK`: Policy issued with `policy_number` (or acknowledged if already issued).
  - `504 Gateway Timeout`: Timeout occurred; transitioned to `NEEDS_RECONCILIATION`.
  - `502 Bad Gateway`: Temporary provider failure; transitioned to `FAILED` for retry.

---

### 3. Read Policy & Audit Trail
`GET /api/policy-requests/{id}`

```bash
curl -X GET http://127.0.0.1:8000/api/policy-requests/1 \
  -H "Accept: application/json"
```

---

### 4. Reconcile Policy After Timeout
`POST /api/policy-requests/{id}/reconcile`

```bash
curl -X POST http://127.0.0.1:8000/api/policy-requests/1/reconcile \
  -H "Accept: application/json"
```

---

### 5. Provider Callback Webhook
`POST /api/mock-insurer/callback`

```bash
curl -X POST http://127.0.0.1:8000/api/mock-insurer/callback \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "AGT-2026-0001",
    "event": "SUCCESS",
    "policy_number": "POL-94821038"
  }'
```

---

### 6. List Requests with Pagination and Filter
`GET /api/policy-requests?status=ISSUED&per_page=10`

```bash
curl -X GET "http://127.0.0.1:8000/api/policy-requests?status=ISSUED&per_page=10" \
  -H "Accept: application/json"
```

---

## 8. Automated Verification Script

A bash script is provided in `examples/` to test the full lifecycle:
```bash
./examples/api_demo.sh
```

---

## 9. Known Assumptions & Limitations

1. **Mock Insurer In-Memory Simulation**:
   - The insurer mock records policies in `mock_insurer_policies` to simulate remote state without external network dependencies.
2. **Currency Precision**:
   - Handled using `decimal:2` database columns and PHP decimal arithmetic to prevent IEEE 754 floating-point inaccuracies.
3. **Queue-less Synchronous Architecture**:
   - Per assignment constraints ("standard Laravel patterns, no UI or queues needed"), calls are handled synchronously with transaction wrappers. In high-volume production, external insurer HTTP calls would be offloaded to Laravel Queues with exponential backoff and jitter.
