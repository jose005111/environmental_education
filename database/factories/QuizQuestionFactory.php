<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use Database\Factories\Concerns\PortugueseText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    use PortugueseText;

    protected $model = QuizQuestion::class;

    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'statement' => $this->ptSentence(),
            'explanation' => $this->ptSentence(),
            'position' => 0,
        ];
    }

    public function withOptions(int $count = 4): static
    {
        return $this->afterCreating(function (QuizQuestion $question) use ($count): void {
            if ($count < 2) {
                return;
            }

            QuizOption::factory()->for($question, 'question')->correct()->create(['position' => 1]);
            QuizOption::factory()->count($count - 1)->for($question, 'question')->create(['position' => 2]);
        });
    }
}
