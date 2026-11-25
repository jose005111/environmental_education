<?php

namespace App\Actions\Quizzes;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class DeleteQuiz
{
    public function execute(Quiz $quiz): void
    {
        $hasAnswers = $quiz->attempts()->exists()
            || $quiz->questions()->whereHas('answers')->exists()
            || $quiz->questions()
                ->whereHas('options', fn (Builder $query) => $query->whereHas('answers'))
                ->exists();

        if ($hasAnswers) {
            throw new QuizConflictException('Não é possível apagar um questionário com tentativas registadas.');
        }

        DB::transaction(fn () => $quiz->delete());
    }
}
