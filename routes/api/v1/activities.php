<?php

use App\Http\Controllers\Api\V1\ActivityController;
use Illuminate\Support\Facades\Route;

Route::get('/activities', [ActivityController::class, 'index'])
    ->name('api.v1.activities.index');

Route::get('/activities/{slug}', [ActivityController::class, 'show'])
    ->name('api.v1.activities.show');

Route::post('/activities', [ActivityController::class, 'store'])
    ->middleware('auth:sanctum')
    ->name('api.v1.activities.store');

Route::put('/activities/{activity}', [ActivityController::class, 'update'])
    ->middleware('auth:sanctum')
    ->name('api.v1.activities.update');

Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])
    ->middleware('auth:sanctum')
    ->name('api.v1.activities.destroy');

Route::post('/activities/{activity}/join', [ActivityController::class, 'join'])
    ->middleware('auth:sanctum')
    ->name('api.v1.activities.join');

Route::post('/activities/{activity}/submit', [ActivityController::class, 'submit'])
    ->middleware('auth:sanctum')
    ->name('api.v1.activities.submit');

Route::get('/activity-participations', [ActivityController::class, 'participations'])
    ->middleware('auth:sanctum')
    ->name('api.v1.activities.participations.index');

Route::patch('/activity-participations/{participation}/review', [ActivityController::class, 'review'])
    ->middleware('auth:sanctum')
    ->name('api.v1.activities.participations.review');

Route::get('/me/participations', [ActivityController::class, 'myParticipations'])
    ->middleware('auth:sanctum')
    ->name('api.v1.activities.me.participations');
