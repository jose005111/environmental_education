<?php

use App\Http\Controllers\Api\V1\QuizController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/quizzes', [QuizController::class, 'index'])
        ->name('api.v1.quizzes.index');

    Route::get('/quizzes/{quiz}', [QuizController::class, 'show'])
        ->name('api.v1.quizzes.show');

    Route::get('/quizzes/{quiz}/attempts', [QuizController::class, 'attempts'])
        ->name('api.v1.quizzes.attempts.moderation');

    Route::post('/quizzes', [QuizController::class, 'store'])
        ->name('api.v1.quizzes.store');

    Route::put('/quizzes/{quiz}', [QuizController::class, 'update'])
        ->name('api.v1.quizzes.update');

    Route::delete('/quizzes/{quiz}', [QuizController::class, 'destroy'])
        ->name('api.v1.quizzes.destroy');

    Route::post('/quizzes/{quiz}/attempts', [QuizController::class, 'attempt'])
        ->name('api.v1.quizzes.attempts.store');

    Route::get('/me/attempts', [QuizController::class, 'history'])
        ->name('api.v1.quizzes.attempts.index');
});
