<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'user_id' => User::factory(),
            'total_questions' => 2,
            'correct_count' => 0,
            'completed_at' => now(),
        ];
    }

    public function withAnswers(): static
    {
        return $this->afterCreating(function (QuizAttempt $attempt): void {
            $correct = 0;

            foreach ($attempt->quiz->questions as $question) {
                $option = $question->options->first();

                if ($option === null) {
                    continue;
                }

                $isCorrect = (bool) $option->is_correct;
                $correct += $isCorrect ? 1 : 0;

                QuizAttemptAnswer::factory()
                    ->for($attempt, 'attempt')
                    ->for($question, 'question')
                    ->for($option, 'option')
                    ->create(['is_correct' => $isCorrect]);
            }

            $attempt->update([
                'total_questions' => $attempt->quiz->questions()->count(),
                'correct_count' => $correct,
            ]);
        });
    }
}
