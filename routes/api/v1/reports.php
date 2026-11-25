<?php

use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/public', [ReportController::class, 'publicIndex'])
    ->name('api.v1.reports.public');

Route::get('/reports/{report}', [ReportController::class, 'show'])
    ->name('api.v1.reports.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reports', [ReportController::class, 'store'])
        ->name('api.v1.reports.store');

    Route::get('/me/reports', [ReportController::class, 'mine'])
        ->name('api.v1.reports.mine');

    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('role:moderador,administrador')
        ->name('api.v1.reports.index');

    Route::patch('/reports/{report}/assign', [ReportController::class, 'assign'])
        ->name('api.v1.reports.assign');

    Route::patch('/reports/{report}/status', [ReportController::class, 'changeStatus'])
        ->name('api.v1.reports.status');
});
