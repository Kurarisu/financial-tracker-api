<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Health / Ping test route
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is running properly!',
        'laravel_version' => app()->version(),
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'activity.log'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/me', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    Route::apiResource('accounts', AccountController::class);
    Route::post('/accounts/{account}/reconcile', [AccountController::class, 'reconcile']);
    Route::post('/accounts/{account}/opening-balance', [AccountController::class, 'setOpeningBalance']);

    Route::apiResource('categories', CategoryController::class);
    Route::put('/categories/{category}/budget', [CategoryController::class, 'setBudget']);
    Route::post('/receipts/scan', [ReceiptController::class, 'scan']);
    Route::post('/transactions', [ReceiptController::class, 'store']);
    Route::get('/transactions/{transaction}/attachment', [ReceiptController::class, 'show']);
    Route::apiResource('transactions', TransactionController::class)->except(['store']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    Route::get('/reports/category-breakdown', [ReportController::class, 'categoryBreakdown']);
    Route::get('/reports/cashflow-trend', [ReportController::class, 'cashflowTrend']);
    Route::get('/reports/account-distribution', [ReportController::class, 'accountDistribution']);
    Route::get('/reports/export', [ReportController::class, 'export']);
    Route::post('/reports/export-month-directed', [ReportController::class, 'exportMonthDirected']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Account deletion removes the actor before the activity logger can persist its entry.
Route::middleware('auth:sanctum')->delete('/auth/me', [AuthController::class, 'destroyAccount']);

// Tasks CRUD API
Route::apiResource('tasks', TaskController::class);
