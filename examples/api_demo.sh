#!/usr/bin/env bash
# PolicyFlow Demonstration Script
# Demonstrates Intake, Duplicate Prevention, Submit, and Reconcile flows

BASE_URL="http://127.0.0.1:8000"

echo "======================================================"
echo " PolicyFlow API Demonstration"
echo "======================================================"

echo ""
echo "1. Creating new Policy Request (POST /api/policy-requests)..."
INTAKE_RESPONSE=$(curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "DEMO-REQ-001",
    "agent_id": "AGENT-42",
    "customer_name": "Demo Traveler",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }')
echo "${INTAKE_RESPONSE}"

POLICY_ID=$(echo "${INTAKE_RESPONSE}" | grep -o '"id":[0-9]*' | head -1 | cut -d':' -f2)

echo ""
echo "2. Submitting IDENTICAL Request (Testing Duplicate Intake)..."
DUPLICATE_RESPONSE=$(curl -s -X POST "${BASE_URL}/api/policy-requests" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "client_request_id": "DEMO-REQ-001",
    "agent_id": "AGENT-42",
    "customer_name": "Demo Traveler",
    "date_of_birth": "1998-04-10",
    "destination": "INTERNATIONAL",
    "trip_start": "2026-11-10",
    "trip_end": "2026-11-14",
    "plan_code": "PLUS",
    "sum_insured": 300000
  }')
echo "${DUPLICATE_RESPONSE}"

if [ -n "${POLICY_ID}" ]; then
  echo ""
  echo "3. Submitting Policy for Issuance (POST /api/policy-requests/${POLICY_ID}/submit)..."
  SUBMIT_RESPONSE=$(curl -s -X POST "${BASE_URL}/api/policy-requests/${POLICY_ID}/submit" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"scenario": "SUCCESS"}')
  echo "${SUBMIT_RESPONSE}"

  echo ""
  echo "4. Reading Policy Details & Audit Trail (GET /api/policy-requests/${POLICY_ID})..."
  curl -s -X GET "${BASE_URL}/api/policy-requests/${POLICY_ID}" \
    -H "Accept: application/json"
  echo ""

  echo ""
  echo "5. Sending Duplicate Callback (POST /api/mock-insurer/callback)..."
  CALLBACK_RESPONSE=$(curl -s -X POST "${BASE_URL}/api/mock-insurer/callback" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{
      "client_request_id": "DEMO-REQ-001",
      "event": "SUCCESS",
      "policy_number": "POL-ATTEMPTED-OVERWRITE"
    }')
  echo "${CALLBACK_RESPONSE}"
fi

echo ""
echo "======================================================"
echo " Demo completed."
echo "======================================================"
