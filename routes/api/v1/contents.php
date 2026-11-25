<?php

use App\Http\Controllers\Api\V1\ContentController;
use Illuminate\Support\Facades\Route;

Route::get('/contents', [ContentController::class, 'index'])
    ->name('api.v1.contents.index');

Route::get('/contents/{slug}', [ContentController::class, 'show'])
    ->name('api.v1.contents.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/contents', [ContentController::class, 'store'])
        ->name('api.v1.contents.store');

    Route::put('/contents/{content}', [ContentController::class, 'update'])
        ->name('api.v1.contents.update');

    Route::patch('/contents/{content}/publish', [ContentController::class, 'publish'])
        ->name('api.v1.contents.publish');

    Route::delete('/contents/{content}', [ContentController::class, 'destroy'])
        ->name('api.v1.contents.destroy');
});
