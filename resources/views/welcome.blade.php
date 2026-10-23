<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolicyFlow | Insurance Issuance &amp; Reconciliation Engine</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <!-- Navbar -->
    <header class="border-b border-slate-800 bg-slate-900/90 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-sky-400 flex items-center justify-center font-bold text-xl text-white shadow-lg shadow-indigo-500/20">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                        PolicyFlow <span class="text-xs font-mono px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">v1.0</span>
                    </h1>
                    <p class="text-xs text-slate-400">TrippersShield Campus Engineering Challenge</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> Engine Online
                </span>
                <a href="#quick-demo" class="px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-xs font-semibold text-white transition shadow-sm">
                    Interactive Console
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Overview Banner -->
        <section class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Architecture</div>
                <div class="text-lg font-bold text-white">Idempotent Engine</div>
                <div class="text-xs text-slate-400 mt-1">Composite DB unique index + transactions</div>
            </div>
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Pricing Rule</div>
                <div class="text-lg font-bold text-indigo-400">Deterministic Math</div>
                <div class="text-xs text-slate-400 mt-1">Tested against ₹225.00 spec example</div>
            </div>
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">State Machine</div>
                <div class="text-lg font-bold text-amber-400">Forward-Only Audit</div>
                <div class="text-xs text-slate-400 mt-1">Rejects illegal state regressions</div>
            </div>
            <div class="bg-slate-800/60 border border-slate-700/60 rounded-xl p-4">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Automated Tests</div>
                <div class="text-lg font-bold text-emerald-400">26 / 26 Passed</div>
                <div class="text-xs text-slate-400 mt-1">120 assertions in Unit &amp; Feature</div>
            </div>
        </section>

        <!-- Interactive Testing Console -->
        <section id="quick-demo" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Intake Form -->
            <div class="lg:col-span-6 bg-slate-800/40 border border-slate-700/60 rounded-2xl p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-700/60 pb-3">
                    <div>
                        <h2 class="text-base font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-file-circle-plus text-indigo-400"></i> Policy Intake Console
                        </h2>
                        <p class="text-xs text-slate-400">Submit requests and observe idempotency &amp; calculations in real time</p>
                    </div>
                    <button onclick="fillSpecExample()" class="text-xs bg-slate-700/60 hover:bg-slate-700 text-indigo-300 px-3 py-1.5 rounded-lg border border-slate-600 transition">
                        Reset to Spec Example (₹225)
                    </button>
                </div>

                <form id="intake-form" onsubmit="event.preventDefault(); submitIntake();" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Client Request ID</label>
                            <input type="text" id="client_request_id" value="AGT-2026-0001" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 font-mono" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Agent ID</label>
                            <input type="text" id="agent_id" value="AGENT-17" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500 font-mono" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Customer Name</label>
                            <input type="text" id="customer_name" value="Sample Customer" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Date of Birth</label>
                            <input type="date" id="date_of_birth" value="1998-04-10" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Destination</label>
                            <select id="destination" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                <option value="INTERNATIONAL" selected>INTERNATIONAL (1.80x)</option>
                                <option value="DOMESTIC">DOMESTIC (1.00x)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Plan Code</label>
                            <select id="plan_code" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                <option value="BASIC">BASIC (₹12/day)</option>
                                <option value="PLUS" selected>PLUS (₹20/day)</option>
                                <option value="PREMIUM">PREMIUM (₹30/day)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Sum Insured</label>
                            <select id="sum_insured" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                <option value="100000">₹100,000 (1.00x)</option>
                                <option value="300000" selected>₹300,000 (1.25x)</option>
                                <option value="500000">₹500,000 (1.50x)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Trip Start</label>
                            <input type="date" id="trip_start" value="2026-11-10" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-1">Trip End</label>
                            <input type="date" id="trip_end" value="2026-11-14" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" required>
                        </div>
                    </div>

                    <div class="pt-2 flex flex-wrap gap-2">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition shadow-lg shadow-indigo-600/25 flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane"></i> POST /api/policy-requests
                        </button>
                        <button type="button" onclick="submitDuplicateIntake()" class="bg-amber-600/80 hover:bg-amber-500 text-white font-semibold py-2.5 px-4 rounded-xl text-sm transition flex items-center gap-2" title="Tests that submitting the exact same request again returns 200 without duplicates">
                            <i class="fa-solid fa-clone"></i> Test Duplicate Intake
                        </button>
                    </div>
                </form>

                <!-- Action Toolbar for Created Policy -->
                <div id="policy-actions" class="border-t border-slate-700/60 pt-4 space-y-3 hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Policy Actions (ID: <span id="active-policy-id" class="text-indigo-400"></span>)</span>
                        <span id="active-policy-status" class="text-xs px-2 py-0.5 rounded font-mono font-semibold"></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <button onclick="submitIssuance('SUCCESS')" class="bg-emerald-600/80 hover:bg-emerald-500 text-white text-xs font-medium py-2 px-3 rounded-lg transition text-center">
                            <i class="fa-solid fa-check-circle block mb-1"></i> Issue Policy
                        </button>
                        <button onclick="submitIssuance('TIMEOUT_AFTER_ISSUE')" class="bg-amber-600/80 hover:bg-amber-500 text-white text-xs font-medium py-2 px-3 rounded-lg transition text-center">
                            <i class="fa-solid fa-clock block mb-1"></i> Simulate Timeout
                        </button>
                        <button onclick="reconcilePolicy()" class="bg-sky-600/80 hover:bg-sky-500 text-white text-xs font-medium py-2 px-3 rounded-lg transition text-center">
                            <i class="fa-solid fa-arrows-rotate block mb-1"></i> Reconcile
                        </button>
                        <button onclick="simulateCallback()" class="bg-purple-600/80 hover:bg-purple-500 text-white text-xs font-medium py-2 px-3 rounded-lg transition text-center">
                            <i class="fa-solid fa-bell block mb-1"></i> Test Callback
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: Real-time Response Output & Audit Trail -->
            <div class="lg:col-span-6 bg-slate-800/40 border border-slate-700/60 rounded-2xl p-6 flex flex-col">
                <div class="flex items-center justify-between border-b border-slate-700/60 pb-3 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-terminal text-sky-400"></i>
                        <span class="text-sm font-bold text-white">Live API Response &amp; Audit Inspector</span>
                    </div>
                    <span id="http-badge" class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-400 border border-slate-700">Ready</span>
                </div>

                <div class="flex-1 bg-slate-950 border border-slate-800 rounded-xl p-4 font-mono text-xs overflow-auto max-h-[440px] text-slate-300" id="response-output">
// Click "POST /api/policy-requests" or "Test Duplicate Intake" to execute live requests.
// You will see the HTTP status code, premium calculation breakdown, and audit transitions here.
                </div>

                <div class="mt-4 pt-3 border-t border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                    <span>Active Endpoint: <span id="current-endpoint" class="text-indigo-400 font-mono">None</span></span>
                    <button onclick="clearConsole()" class="hover:text-slate-200 text-xs">Clear Console</button>
                </div>
            </div>
        </section>

        <!-- API Reference Grid -->
        <section class="bg-slate-800/30 border border-slate-700/60 rounded-2xl p-6">
            <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                <i class="fa-solid fa-book-bookmark text-indigo-400"></i> Core REST Endpoints Reference
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded font-bold font-mono bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">POST</span>
                        <span class="font-mono text-slate-200">/api/policy-requests</span>
                    </div>
                    <p class="text-slate-400">Creates or safely returns existing request (idempotent 201/200).</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded font-bold font-mono bg-sky-500/10 text-sky-400 border border-sky-500/20">GET</span>
                        <span class="font-mono text-slate-200">/api/policy-requests/{id}</span>
                    </div>
                    <p class="text-slate-400">Retrieves policy status, snapshot premium, and complete audit trail.</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded font-bold font-mono bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">POST</span>
                        <span class="font-mono text-slate-200">/api/policy-requests/{id}/submit</span>
                    </div>
                    <p class="text-slate-400">Submits to insurer; idempotently prevents duplicate issuance.</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded font-bold font-mono bg-amber-500/10 text-amber-400 border border-amber-500/20">POST</span>
                        <span class="font-mono text-slate-200">/api/policy-requests/{id}/reconcile</span>
                    </div>
                    <p class="text-slate-400">Queries provider to safely resolve timeouts without duplicate policies.</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded font-bold font-mono bg-purple-500/10 text-purple-400 border border-purple-500/20">POST</span>
                        <span class="font-mono text-slate-200">/api/mock-insurer/callback</span>
                    </div>
                    <p class="text-slate-400">Asynchronous provider webhook; ignores repeated callbacks safely.</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-3">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 rounded font-bold font-mono bg-sky-500/10 text-sky-400 border border-sky-500/20">GET</span>
                        <span class="font-mono text-slate-200">/api/policy-requests</span>
                    </div>
                    <p class="text-slate-400">List requests with status filtering (?status=ISSUED) and pagination.</p>
                </div>
            </div>
        </section>
    </main>

    <script>
        let currentPolicy = null;

        function fillSpecExample() {
            document.getElementById('client_request_id').value = 'AGT-2026-0001';
            document.getElementById('agent_id').value = 'AGENT-17';
            document.getElementById('customer_name').value = 'Sample Customer';
            document.getElementById('date_of_birth').value = '1998-04-10';
            document.getElementById('destination').value = 'INTERNATIONAL';
            document.getElementById('plan_code').value = 'PLUS';
            document.getElementById('sum_insured').value = '300000';
            document.getElementById('trip_start').value = '2026-11-10';
            document.getElementById('trip_end').value = '2026-11-14';
        }

        function getPayload() {
            return {
                client_request_id: document.getElementById('client_request_id').value,
                agent_id: document.getElementById('agent_id').value,
                customer_name: document.getElementById('customer_name').value,
                date_of_birth: document.getElementById('date_of_birth').value,
                destination: document.getElementById('destination').value,
                trip_start: document.getElementById('trip_start').value,
                trip_end: document.getElementById('trip_end').value,
                plan_code: document.getElementById('plan_code').value,
                sum_insured: Number(document.getElementById('sum_insured').value)
            };
        }

        async function submitIntake() {
            const payload = getPayload();
            document.getElementById('current-endpoint').innerText = 'POST /api/policy-requests';
            setResponse('Executing request...', 'PENDING', 'bg-slate-800 text-slate-300');

            try {
                const res = await fetch('/api/policy-requests', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                handleApiResponse(res.status, data);

                if (data.data && data.data.id) {
                    currentPolicy = data.data;
                    updatePolicyActions(currentPolicy);
                }
            } catch (err) {
                setResponse(err.message, 'ERROR', 'bg-rose-500/20 text-rose-400 border-rose-500/30');
            }
        }

        async function submitDuplicateIntake() {
            // Simply submit again with same fields
            await submitIntake();
        }

        async function submitIssuance(scenario) {
            if (!currentPolicy) return;
            const endpoint = `/api/policy-requests/${currentPolicy.id}/submit`;
            document.getElementById('current-endpoint').innerText = `POST ${endpoint}`;

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ scenario: scenario })
                });
                const data = await res.json();
                handleApiResponse(res.status, data);
                if (data.data) {
                    currentPolicy = data.data;
                    updatePolicyActions(currentPolicy);
                }
            } catch (err) {
                setResponse(err.message, 'ERROR', 'bg-rose-500/20 text-rose-400 border-rose-500/30');
            }
        }

        async function reconcilePolicy() {
            if (!currentPolicy) return;
            const endpoint = `/api/policy-requests/${currentPolicy.id}/reconcile`;
            document.getElementById('current-endpoint').innerText = `POST ${endpoint}`;

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                handleApiResponse(res.status, data);
                if (data.data) {
                    currentPolicy = data.data;
                    updatePolicyActions(currentPolicy);
                }
            } catch (err) {
                setResponse(err.message, 'ERROR', 'bg-rose-500/20 text-rose-400 border-rose-500/30');
            }
        }

        async function simulateCallback() {
            if (!currentPolicy) return;
            const endpoint = '/api/mock-insurer/callback';
            document.getElementById('current-endpoint').innerText = `POST ${endpoint}`;

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        client_request_id: currentPolicy.client_request_id,
                        event: 'SUCCESS',
                        policy_number: currentPolicy.policy_number || 'POL-WEBHOOK-' + Math.floor(Math.random() * 100000)
                    })
                });
                const data = await res.json();
                handleApiResponse(res.status, data);
            } catch (err) {
                setResponse(err.message, 'ERROR', 'bg-rose-500/20 text-rose-400 border-rose-500/30');
            }
        }

        function updatePolicyActions(policy) {
            document.getElementById('policy-actions').classList.remove('hidden');
            document.getElementById('active-policy-id').innerText = policy.id;
            const statusEl = document.getElementById('active-policy-status');
            statusEl.innerText = policy.status;
            
            if (policy.status === 'ISSUED') {
                statusEl.className = 'text-xs px-2 py-0.5 rounded font-mono font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
            } else if (policy.status === 'NEEDS_RECONCILIATION') {
                statusEl.className = 'text-xs px-2 py-0.5 rounded font-mono font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30';
            } else if (policy.status === 'FAILED') {
                statusEl.className = 'text-xs px-2 py-0.5 rounded font-mono font-semibold bg-rose-500/20 text-rose-300 border border-rose-500/30';
            } else {
                statusEl.className = 'text-xs px-2 py-0.5 rounded font-mono font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30';
            }
        }

        function handleApiResponse(status, data) {
            let badgeClass = 'bg-slate-800 text-slate-300 border border-slate-700';
            if (status >= 200 && status < 300) {
                badgeClass = 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
            } else if (status >= 400 && status < 500) {
                badgeClass = 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
            } else if (status >= 500) {
                badgeClass = 'bg-rose-500/20 text-rose-300 border border-rose-500/30';
            }
            setResponse(JSON.stringify(data, null, 2), `HTTP ${status}`, badgeClass);
        }

        function setResponse(text, badgeText, badgeClass) {
            document.getElementById('response-output').innerText = text;
            const badge = document.getElementById('http-badge');
            badge.innerText = badgeText;
            badge.className = `text-xs font-mono px-2 py-0.5 rounded ${badgeClass}`;
        }

        function clearConsole() {
            setResponse('// Console cleared.', 'Ready', 'bg-slate-800 text-slate-400 border border-slate-700');
            document.getElementById('current-endpoint').innerText = 'None';
        }
    </script>
</body>
</html>
