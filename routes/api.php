<?php

use App\Http\Controllers\Api\MockInsurerController;
use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PolicyFlow REST API Routes
|--------------------------------------------------------------------------
| Fault-Tolerant Insurance Issuance & Reconciliation Engine
*/

Route::prefix('policy-requests')->group(function () {
    Route::post('/', [PolicyRequestController::class, 'store']);
    Route::get('/', [PolicyRequestController::class, 'index']);
    Route::get('/{id}', [PolicyRequestController::class, 'show'])->whereNumber('id');
    Route::post('/{id}/submit', [PolicyRequestController::class, 'submit'])->whereNumber('id');
    Route::post('/{id}/reconcile', [PolicyRequestController::class, 'reconcile'])->whereNumber('id');
});

Route::prefix('mock-insurer')->group(function () {
    Route::post('/callback', [MockInsurerController::class, 'callback']);
    Route::get('/status/{client_request_id}', [MockInsurerController::class, 'status']);
});
