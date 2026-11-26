<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ResidentOverviewController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/public/home', [SiteController::class, 'home'])->name('api.v1.public.home');

    Route::middleware(['auth:sanctum', 'role:administrador'])->group(function () {
        Route::get('/admin/site', [SiteController::class, 'show'])->name('api.v1.admin.site.show');
        Route::put('/admin/site', [SiteController::class, 'update'])->name('api.v1.admin.site.update');
    });

    Route::get('/categories', [CategoryController::class, 'index'])
        ->name('api.v1.categories.index');

    Route::get('/categories/{category}', [CategoryController::class, 'show'])
        ->name('api.v1.categories.show');

    Route::get('/zones', [ZoneController::class, 'index'])
        ->name('api.v1.zones.index');

    Route::middleware(['auth:sanctum', 'role:administrador'])->group(function () {
        Route::post('/zones', [ZoneController::class, 'store'])->name('api.v1.zones.store');
        Route::put('/zones/{zone}', [ZoneController::class, 'update'])->name('api.v1.zones.update');
        Route::delete('/zones/{zone}', [ZoneController::class, 'destroy'])->name('api.v1.zones.destroy');

        Route::post('/categories', [CategoryController::class, 'store'])->name('api.v1.categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('api.v1.categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('api.v1.categories.destroy');

        Route::get('/users', [UserController::class, 'index'])->name('api.v1.users.index');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('api.v1.users.update');
    });

    Route::middleware(['auth:sanctum', 'role:moderador,administrador'])->group(function () {
        Route::get('/residents', [ResidentOverviewController::class, 'index'])->name('api.v1.residents.index');
        Route::get('/residents/{resident}', [ResidentOverviewController::class, 'show'])->name('api.v1.residents.show');
        Route::get('/residents/{resident}/records', [ResidentOverviewController::class, 'records'])->name('api.v1.residents.records');
    });

    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('api.v1.auth.register');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('api.v1.auth.login');

    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset')
        ->name('api.v1.auth.forgot-password');

    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:password-reset')
        ->name('api.v1.auth.reset-password');

    Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['auth:sanctum', 'signed'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('api.v1.auth.logout');

        Route::get('/me', [AuthController::class, 'me'])
            ->name('api.v1.me');

        Route::put('/me', [AuthController::class, 'updateProfile'])
            ->name('api.v1.me.update');

        Route::post('/auth/email/verification-notification', [AuthController::class, 'sendVerificationNotification'])
            ->middleware('throttle:verification')
            ->name('api.v1.auth.verification-notification');
    });

    foreach (glob(__DIR__.'/api/v1/*.php') as $routeFile) {
        require $routeFile;
    }
});
