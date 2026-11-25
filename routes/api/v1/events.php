<?php

use App\Http\Controllers\Api\V1\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/events', [EventController::class, 'index'])
    ->name('api.v1.events.index');

Route::get('/events/{slug}', [EventController::class, 'show'])
    ->name('api.v1.events.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/events', [EventController::class, 'store'])
        ->name('api.v1.events.store');

    Route::put('/events/{event}', [EventController::class, 'update'])
        ->name('api.v1.events.update');

    Route::delete('/events/{event}', [EventController::class, 'destroy'])
        ->name('api.v1.events.destroy');

    Route::post('/events/{event}/register', [EventController::class, 'register'])
        ->name('api.v1.events.register');

    Route::delete('/events/{event}/register', [EventController::class, 'cancelRegistration'])
        ->name('api.v1.events.cancel-registration');

    Route::get('/events/{event}/registrations', [EventController::class, 'registrations'])
        ->name('api.v1.events.registrations');

    Route::patch('/event-registrations/{eventRegistration}', [EventController::class, 'markPresent'])
        ->name('api.v1.events.mark-present');

    Route::get('/me/registrations', [EventController::class, 'myRegistrations'])
        ->name('api.v1.events.my-registrations');
});
